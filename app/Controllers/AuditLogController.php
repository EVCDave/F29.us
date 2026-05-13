<?php
declare(strict_types=1);

class AuditLogController
{
    // ── Audit log list ────────────────────────────────────────────────────────

    public function index(array $params = []): void
    {
        $this->requireAdmin();
        $pdo = Database::get();

        $action     = trim($_GET['action']      ?? '');
        $entityType = trim($_GET['entity_type'] ?? '');
        $userEmail  = trim($_GET['user_email']  ?? '');
        $dateFrom   = trim($_GET['date_from']   ?? '');
        $dateTo     = trim($_GET['date_to']     ?? '');

        $where = [];
        $args  = [];

        if ($action !== '') {
            $where[] = 'al.action LIKE ?';
            $args[]  = '%' . $action . '%';
        }
        if ($entityType !== '') {
            $where[] = 'al.entity_type = ?';
            $args[]  = $entityType;
        }
        if ($userEmail !== '') {
            $where[] = 'u.email LIKE ?';
            $args[]  = '%' . $userEmail . '%';
        }
        if ($dateFrom !== '' && strtotime($dateFrom) !== false) {
            $where[] = 'al.created_at >= ?';
            $args[]  = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '' && strtotime($dateTo) !== false) {
            $where[] = 'al.created_at <= ?';
            $args[]  = $dateTo . ' 23:59:59';
        }

        $sql = "
            SELECT al.id, al.user_id, al.entity_type, al.entity_id, al.action,
                   al.metadata_json, al.created_at, u.email AS user_email
            FROM   audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
        ";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY al.id DESC LIMIT 100';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        View::render('admin/audit_logs', [
            'pageTitle'  => 'Admin: Audit Logs — f29.us Dynamic QR',
            'logs'       => $logs,
            'action'     => $action,
            'entityType' => $entityType,
            'userEmail'  => $userEmail,
            'dateFrom'   => $dateFrom,
            'dateTo'     => $dateTo,
        ]);
    }

    // ── Audit log detail ──────────────────────────────────────────────────────

    public function detail(array $params = []): void
    {
        $this->requireAdmin();
        $logId = (int) ($params['id'] ?? 0);

        if ($logId <= 0) {
            $this->notFound();
        }

        $stmt = Database::get()->prepare("
            SELECT al.*, u.email AS user_email
            FROM   audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE  al.id = ?
            LIMIT  1
        ");
        $stmt->execute([$logId]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$log) {
            $this->notFound();
        }

        $meta = null;
        if ($log['metadata_json'] !== null) {
            $meta = json_decode($log['metadata_json'], true);
        }

        View::render('admin/audit_log_detail', [
            'pageTitle' => 'Admin: Audit Log #' . $logId . ' — f29.us Dynamic QR',
            'log'       => $log,
            'meta'      => $meta,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function requireAdmin(): void
    {
        AuthService::requireAuth();
        if (!AuthService::isAdmin()) {
            $this->forbidden('Admin access required.');
        }
    }

    private function forbidden(string $message = 'Access denied.'): never
    {
        http_response_code(403);
        View::render('errors/forbidden', ['pageTitle' => '403 — Access Denied', 'message' => $message]);
        exit;
    }

    private function notFound(): never
    {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => '404 — Not Found']);
        exit;
    }
}
