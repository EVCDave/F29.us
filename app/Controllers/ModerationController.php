<?php
declare(strict_types=1);

class ModerationController
{
    // ── Moderated links list ──────────────────────────────────────────────────

    public function links(array $params = []): void
    {
        $this->requireAdmin();
        $pdo = Database::get();

        $statusFilter = trim($_GET['status'] ?? 'disabled');
        $ownerFilter  = trim($_GET['owner']  ?? '');
        $slugFilter   = trim($_GET['slug']   ?? '');
        $destFilter   = trim($_GET['dest']   ?? '');

        $validStatuses = ['', 'active', 'paused', 'disabled', 'archived'];
        if (!in_array($statusFilter, $validStatuses, true)) {
            $statusFilter = 'disabled';
        }

        $where = [];
        $args  = [];

        if ($statusFilter !== '') {
            $where[] = 'sl.status = ?';
            $args[]  = $statusFilter;
        }
        if ($ownerFilter !== '') {
            $where[] = 'u.email LIKE ?';
            $args[]  = '%' . $ownerFilter . '%';
        }
        if ($slugFilter !== '') {
            $where[] = 'sl.slug LIKE ?';
            $args[]  = '%' . $slugFilter . '%';
        }
        if ($destFilter !== '') {
            $where[] = 'sl.current_target_url LIKE ?';
            $args[]  = '%' . $destFilter . '%';
        }

        $sql = "
            SELECT
                sl.id, sl.slug, sl.current_target_url, sl.status,
                sl.disabled_reason, sl.disabled_at, sl.created_at,
                qr.id    AS qr_id,
                qr.name  AS qr_name,
                u.email  AS owner_email,
                du.email AS disabled_by_email
            FROM  short_links sl
            LEFT JOIN qr_codes qr ON qr.short_link_id = sl.id
            LEFT JOIN users    u  ON u.id  = sl.user_id
            LEFT JOIN users    du ON du.id = sl.disabled_by_user_id
        ";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY sl.disabled_at DESC, sl.id DESC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        View::render('admin/moderation/links', [
            'pageTitle'    => 'Admin: Moderated Links — f29.us Dynamic QR',
            'links'        => $links,
            'statusFilter' => $statusFilter,
            'ownerFilter'  => $ownerFilter,
            'slugFilter'   => $slugFilter,
            'destFilter'   => $destFilter,
            'flash'        => $flash,
        ]);
    }

    // ── Link detail ───────────────────────────────────────────────────────────

    public function linkDetail(array $params = []): void
    {
        $this->requireAdmin();
        $shortLinkId = (int) ($params['id'] ?? 0);
        $link        = $this->loadShortLink($shortLinkId);

        $cutoff = gmdate('Y-m-d H:i:s', strtotime('-30 days'));
        $stmt   = Database::get()->prepare(
            "SELECT COUNT(*) FROM scan_events WHERE short_link_id = ? AND scanned_at >= ?"
        );
        $stmt->execute([$shortLinkId, $cutoff]);
        $recentScans = (int) $stmt->fetchColumn();

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        View::render('admin/moderation/link_detail', [
            'pageTitle'   => 'Admin: Link #' . $shortLinkId . ' — f29.us Dynamic QR',
            'link'        => $link,
            'recentScans' => $recentScans,
            'flash'       => $flash,
        ]);
    }

    // ── Disable ───────────────────────────────────────────────────────────────

    public function disable(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();
        $adminId     = (int) AuthService::userId();
        $shortLinkId = (int) ($params['id'] ?? 0);
        $link        = $this->loadShortLink($shortLinkId);

        if ($link['status'] === 'disabled') {
            $_SESSION['flash'] = ['type' => 'info', 'text' => 'This link is already disabled.'];
            redirect('/admin/moderation/links/' . $shortLinkId);
        }

        $reason = trim($_POST['disabled_reason'] ?? '');
        $note   = trim($_POST['moderation_note']  ?? '');

        if ($reason === '') {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'A disable reason is required.'];
            redirect('/admin/moderation/links/' . $shortLinkId);
        }

        $reason = mb_substr($reason, 0, 255);
        $now    = gmdate('Y-m-d H:i:s');

        Database::get()->prepare("
            UPDATE short_links
            SET    status              = 'disabled',
                   disabled_reason    = ?,
                   disabled_by_user_id = ?,
                   disabled_at        = ?,
                   moderation_note    = ?,
                   updated_at         = ?
            WHERE  id = ?
        ")->execute([
            $reason,
            $adminId,
            $now,
            $note !== '' ? $note : null,
            $now,
            $shortLinkId,
        ]);

        AuditLogService::log($adminId, 'short_link', $shortLinkId, 'admin_disabled', [
            'slug'            => $link['slug'],
            'old_status'      => $link['status'],
            'disabled_reason' => $reason,
            'has_note'        => $note !== '',
        ]);

        NotificationService::linkDisabled($shortLinkId);

        $_SESSION['flash'] = ['type' => 'success',
            'text' => 'Link #' . $shortLinkId . ' (' . $link['slug'] . ') has been disabled.'];
        redirect('/admin/moderation/links/' . $shortLinkId);
    }

    // ── Admin restore ─────────────────────────────────────────────────────────

    public function restore(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();
        $adminId     = (int) AuthService::userId();
        $shortLinkId = (int) ($params['id'] ?? 0);
        $link        = $this->loadShortLink($shortLinkId);

        if ($link['status'] !== 'disabled') {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'Only disabled links can be restored via moderation.'];
            redirect('/admin/moderation/links/' . $shortLinkId);
        }

        $now = gmdate('Y-m-d H:i:s');

        Database::get()->prepare(
            "UPDATE short_links SET status = 'active', updated_at = ? WHERE id = ?"
        )->execute([$now, $shortLinkId]);

        AuditLogService::log($adminId, 'short_link', $shortLinkId, 'admin_restored', [
            'slug'            => $link['slug'],
            'old_status'      => 'disabled',
            'new_status'      => 'active',
            'disabled_reason' => $link['disabled_reason'],
        ]);

        NotificationService::linkRestored($shortLinkId);

        $_SESSION['flash'] = ['type' => 'success',
            'text' => 'Link #' . $shortLinkId . ' (' . $link['slug'] . ') has been restored to active.'];
        redirect('/admin/moderation/links/' . $shortLinkId);
    }

    // ── Blocked domains list + add form ───────────────────────────────────────

    public function domains(array $params = []): void
    {
        $this->requireAdmin();
        $pdo = Database::get();

        $stmt = $pdo->query("
            SELECT bd.id, bd.domain, bd.reason, bd.is_active, bd.created_at,
                   u.email AS created_by_email
            FROM   blocked_domains bd
            LEFT JOIN users u ON u.id = bd.created_by_user_id
            ORDER  BY bd.is_active DESC, bd.created_at DESC
        ");
        $domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        View::render('admin/moderation/domains', [
            'pageTitle' => 'Admin: Blocked Domains — f29.us Dynamic QR',
            'domains'   => $domains,
            'flash'     => $flash,
        ]);
    }

    // ── Add blocked domain ────────────────────────────────────────────────────

    public function addDomain(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();
        $adminId   = (int) AuthService::userId();
        $rawDomain = trim($_POST['domain'] ?? '');
        $reason    = trim($_POST['reason'] ?? '');

        if ($rawDomain === '') {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'Domain is required.'];
            redirect('/admin/moderation/domains');
        }

        // Accept bare domains or URLs — strip any scheme the admin may have included
        $forParsing = preg_match('#^https?://#i', $rawDomain) ? $rawDomain : 'https://' . $rawDomain;
        $host       = DomainBlocklistService::extractHost($forParsing);

        if ($host === null) {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'Please enter a valid domain (e.g. example.com).'];
            redirect('/admin/moderation/domains');
        }

        $normalized = DomainBlocklistService::normalizeDomain($host);

        if (!DomainBlocklistService::isValidDomain($normalized)) {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'Please enter a valid domain (e.g. example.com).'];
            redirect('/admin/moderation/domains');
        }

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        try {
            $pdo->prepare("
                INSERT INTO blocked_domains
                    (domain, reason, is_active, created_by_user_id, created_at, updated_at)
                VALUES (?, ?, 1, ?, ?, ?)
            ")->execute([
                $normalized,
                $reason !== '' ? $reason : null,
                $adminId,
                $now,
                $now,
            ]);

            $domainId = (int) $pdo->lastInsertId();

            AuditLogService::log($adminId, 'blocked_domain', $domainId, 'domain_blocked', [
                'domain' => $normalized,
                'reason' => $reason !== '' ? $reason : null,
            ]);

            $_SESSION['flash'] = ['type' => 'success',
                'text' => 'Domain "' . $normalized . '" has been added to the blocklist.'];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $_SESSION['flash'] = ['type' => 'error',
                    'text' => 'That domain is already in the blocklist.'];
            } else {
                throw $e;
            }
        }

        redirect('/admin/moderation/domains');
    }

    // ── Toggle blocked domain active/inactive ─────────────────────────────────

    public function toggleDomain(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();
        $adminId  = (int) AuthService::userId();
        $domainId = (int) ($params['id'] ?? 0);

        if ($domainId <= 0) {
            $this->notFound();
        }

        $pdo  = Database::get();
        $stmt = $pdo->prepare(
            "SELECT id, domain, is_active FROM blocked_domains WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$domainId]);
        $domain = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$domain) {
            $this->notFound();
        }

        $newActive = $domain['is_active'] ? 0 : 1;
        $now       = gmdate('Y-m-d H:i:s');

        $pdo->prepare(
            "UPDATE blocked_domains SET is_active = ?, updated_at = ? WHERE id = ?"
        )->execute([$newActive, $now, $domainId]);

        AuditLogService::log($adminId, 'blocked_domain', $domainId, 'domain_toggled', [
            'domain'     => $domain['domain'],
            'old_active' => (bool) $domain['is_active'],
            'new_active' => (bool) $newActive,
        ]);

        $label = $newActive ? 'activated' : 'deactivated';
        $_SESSION['flash'] = ['type' => 'success',
            'text' => 'Domain "' . $domain['domain'] . '" has been ' . $label . '.'];
        redirect('/admin/moderation/domains');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function requireAdmin(): void
    {
        AuthService::requireAuth();
        if (!AuthService::isAdmin()) {
            $this->forbidden('Admin access required.');
        }
    }

    private function loadShortLink(int $id): array
    {
        if ($id <= 0) {
            $this->notFound();
        }

        $stmt = Database::get()->prepare("
            SELECT
                sl.id, sl.slug, sl.current_target_url, sl.status,
                sl.disabled_reason, sl.disabled_by_user_id, sl.disabled_at,
                sl.moderation_note, sl.created_at, sl.updated_at,
                qr.id    AS qr_id,
                qr.name  AS qr_name,
                u.id     AS owner_id,
                u.email  AS owner_email,
                du.email AS disabled_by_email
            FROM  short_links sl
            LEFT JOIN qr_codes qr ON qr.short_link_id = sl.id
            LEFT JOIN users    u  ON u.id  = sl.user_id
            LEFT JOIN users    du ON du.id = sl.disabled_by_user_id
            WHERE sl.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->notFound();
        }

        return $row;
    }

    private function forbidden(string $message = 'Access denied.'): never
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
