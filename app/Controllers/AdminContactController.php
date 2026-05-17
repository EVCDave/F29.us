<?php
declare(strict_types=1);

/**
 * Admin review for messages submitted through the public contact form.
 *
 * Lightweight intake — no threading, no replies, no public ticket numbers.
 * Admins can filter, view detail, change status (new / reviewed / closed),
 * and record an internal note. Replies happen out-of-band over email using
 * the submitter's address.
 */
class AdminContactController
{
    private const VALID_STATUSES = ['new', 'reviewed', 'closed'];
    private const LIST_LIMIT     = 100;
    private const NOTE_MAX_CHARS = 5000;

    public function index(array $params = []): void
    {
        $this->requireAdmin();

        $statusFilter   = trim((string) ($_GET['status']   ?? ''));
        $categoryFilter = trim((string) ($_GET['category'] ?? ''));
        $search         = trim((string) ($_GET['q']        ?? ''));

        if ($statusFilter !== '' && !in_array($statusFilter, self::VALID_STATUSES, true)) {
            $statusFilter = '';
        }
        if ($categoryFilter !== '' && !isset(ContactController::ALL_CATEGORIES[$categoryFilter])) {
            $categoryFilter = '';
        }

        $where = [];
        $args  = [];

        if ($statusFilter !== '') {
            $where[] = 'cm.status = ?';
            $args[]  = $statusFilter;
        }
        if ($categoryFilter !== '') {
            $where[] = 'cm.category = ?';
            $args[]  = $categoryFilter;
        }
        if ($search !== '') {
            $where[] = '(cm.email LIKE ? OR cm.subject LIKE ?)';
            $args[]  = '%' . $search . '%';
            $args[]  = '%' . $search . '%';
        }

        $sql = "
            SELECT cm.id, cm.created_at, cm.status, cm.category, cm.subject,
                   cm.name, cm.email, cm.user_id,
                   u.email AS user_email
            FROM   contact_messages cm
            LEFT JOIN users u ON u.id = cm.user_id
        ";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY cm.created_at DESC, cm.id DESC LIMIT ' . self::LIST_LIMIT;

        $stmt = Database::get()->prepare($sql);
        $stmt->execute($args);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $newCount = (int) Database::get()
            ->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")
            ->fetchColumn();

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        View::render('admin/contact_messages', [
            'pageTitle'      => 'Contact Messages — Admin',
            'messages'       => $messages,
            'categories'     => ContactController::ALL_CATEGORIES,
            'statusFilter'   => $statusFilter,
            'categoryFilter' => $categoryFilter,
            'search'         => $search,
            'newCount'       => $newCount,
            'flash'          => $flash,
        ]);
    }

    public function detail(array $params = []): void
    {
        $this->requireAdmin();

        $id = (int) ($params['id'] ?? 0);
        $row = $this->loadMessage($id);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        View::render('admin/contact_message_detail', [
            'pageTitle'  => 'Contact Message #' . $id . ' — Admin',
            'message'    => $row,
            'categories' => ContactController::ALL_CATEGORIES,
            'flash'      => $flash,
        ]);
    }

    public function updateStatus(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $id  = (int) ($params['id'] ?? 0);
        $row = $this->loadMessage($id);

        $newStatus = trim((string) ($_POST['status'] ?? ''));
        if (!in_array($newStatus, self::VALID_STATUSES, true)) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'Invalid status.'];
            redirect('/admin/contact-messages/' . $id);
        }

        if ($newStatus === $row['status']) {
            $_SESSION['flash'] = ['type' => 'info', 'text' => 'Status unchanged.'];
            redirect('/admin/contact-messages/' . $id);
        }

        $pdo       = Database::get();
        $now       = gmdate('Y-m-d H:i:s');
        $adminId   = (int) AuthService::userId();
        $oldStatus = (string) $row['status'];

        if ($newStatus === 'new') {
            // Reopen — clear handled fields so the audit / "handled by" view stays accurate.
            $pdo->prepare("
                UPDATE contact_messages
                SET status             = 'new',
                    handled_at         = NULL,
                    handled_by_user_id = NULL
                WHERE id = ?
            ")->execute([$id]);
        } else {
            $pdo->prepare("
                UPDATE contact_messages
                SET status             = ?,
                    handled_at         = ?,
                    handled_by_user_id = ?
                WHERE id = ?
            ")->execute([$newStatus, $now, $adminId, $id]);
        }

        AuditLogService::log($adminId, 'contact_message', $id, 'contact_message_status_updated', [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Status updated.'];
        redirect('/admin/contact-messages/' . $id);
    }

    public function updateNote(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $id  = (int) ($params['id'] ?? 0);
        $row = $this->loadMessage($id);

        $note = (string) ($_POST['admin_note'] ?? '');
        if (mb_strlen($note) > self::NOTE_MAX_CHARS) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'text' => 'Internal note must be ' . self::NOTE_MAX_CHARS . ' characters or fewer.',
            ];
            redirect('/admin/contact-messages/' . $id);
        }

        $normalized = trim($note) === '' ? null : $note;
        $adminId    = (int) AuthService::userId();

        Database::get()->prepare(
            "UPDATE contact_messages SET admin_note = ? WHERE id = ?"
        )->execute([$normalized, $id]);

        AuditLogService::log($adminId, 'contact_message', $id, 'contact_message_note_updated', [
            'note_length' => mb_strlen((string) $normalized),
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Note saved.'];
        redirect('/admin/contact-messages/' . $id);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function loadMessage(int $id): array
    {
        if ($id <= 0) {
            $this->notFound();
        }
        $stmt = Database::get()->prepare("
            SELECT cm.*,
                   u.email   AS user_email,
                   h.email   AS handled_by_email
            FROM   contact_messages cm
            LEFT JOIN users u ON u.id = cm.user_id
            LEFT JOIN users h ON h.id = cm.handled_by_user_id
            WHERE  cm.id = ?
            LIMIT  1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->notFound();
        }
        return $row;
    }

    private function requireAdmin(): void
    {
        AuthService::requireAuth();
        if (!AuthService::isAdmin()) {
            $this->forbidden('Admin access required.');
        }
    }

    private function forbidden(string $message): never
    {
        http_response_code(403);
        View::render('errors/forbidden', [
            'pageTitle' => '403 — Access Denied',
            'message'   => $message,
        ]);
        exit;
    }

    private function notFound(): never
    {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => '404 — Not Found']);
        exit;
    }
}
