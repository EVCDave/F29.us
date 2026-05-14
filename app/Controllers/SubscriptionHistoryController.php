<?php
declare(strict_types=1);

class SubscriptionHistoryController
{
    public function index(array $params = []): void
    {
        $this->requireAdmin();
        $pdo = Database::get();

        $userEmail    = trim($_GET['user_email']    ?? '');
        $planId       = (int) ($_GET['plan_id']     ?? 0);
        $status       = trim($_GET['status']        ?? '');
        $billingCycle = trim($_GET['billing_cycle'] ?? '');
        $dateFrom     = trim($_GET['date_from']     ?? '');
        $dateTo       = trim($_GET['date_to']       ?? '');

        $validStatuses = ['active', 'canceled', 'expired', ''];
        if (!in_array($status, $validStatuses, true)) {
            $status = '';
        }
        $validCycles = ['monthly', 'yearly', 'manual', 'free', ''];
        if (!in_array($billingCycle, $validCycles, true)) {
            $billingCycle = '';
        }

        $where = [];
        $args  = [];

        if ($userEmail !== '') {
            $where[] = 'u.email LIKE ?';
            $args[]  = '%' . $userEmail . '%';
        }
        if ($planId > 0) {
            $where[] = 'us.plan_id = ?';
            $args[]  = $planId;
        }
        if ($status !== '') {
            $where[] = 'us.status = ?';
            $args[]  = $status;
        }
        if ($billingCycle !== '') {
            $where[] = 'us.billing_cycle = ?';
            $args[]  = $billingCycle;
        }
        if ($dateFrom !== '' && strtotime($dateFrom) !== false) {
            $where[] = 'us.started_at >= ?';
            $args[]  = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '' && strtotime($dateTo) !== false) {
            $where[] = 'us.started_at <= ?';
            $args[]  = $dateTo . ' 23:59:59';
        }

        $sql = "
            SELECT us.id, us.user_id, us.plan_id, us.status, us.billing_cycle,
                   us.billing_status, us.started_at, us.canceled_at, us.ends_at,
                   us.grandfathered_at,
                   u.email AS user_email,
                   p.display_name AS plan_display_name, p.internal_name AS plan_internal_name
            FROM   user_subscriptions us
            JOIN   users u ON u.id = us.user_id
            JOIN   plans p ON p.id = us.plan_id
        ";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY us.id DESC LIMIT 100';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $plans = $pdo->query(
            "SELECT id, display_name, internal_name FROM plans ORDER BY sort_order ASC, id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        View::render('admin/subscriptions', [
            'pageTitle'     => 'Admin: Subscriptions — f29.us Dynamic QR',
            'subscriptions' => $subscriptions,
            'plans'         => $plans,
            'userEmail'     => $userEmail,
            'planId'        => $planId,
            'status'        => $status,
            'billingCycle'  => $billingCycle,
            'dateFrom'      => $dateFrom,
            'dateTo'        => $dateTo,
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
}
