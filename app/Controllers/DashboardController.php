<?php
declare(strict_types=1);

class DashboardController
{
    public function index(array $params = []): void
    {
        AuthService::requireAuth();

        $userId = (int) AuthService::userId();
        $pdo    = Database::get();

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*)                                              AS total,
                SUM(CASE WHEN status = 'active'   THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'paused'   THEN 1 ELSE 0 END) AS paused,
                SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) AS archived,
                SUM(CASE WHEN status = 'disabled' THEN 1 ELSE 0 END) AS disabled
            FROM short_links
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        View::render('dashboard', [
            'pageTitle' => 'Dashboard — f29.us Dynamic QR',
            'user'      => AuthService::currentUser(),
            'counts'    => [
                'total'    => (int) ($counts['total']    ?? 0),
                'active'   => (int) ($counts['active']   ?? 0),
                'paused'   => (int) ($counts['paused']   ?? 0),
                'archived' => (int) ($counts['archived'] ?? 0),
                'disabled' => (int) ($counts['disabled'] ?? 0),
            ],
        ]);
    }
}
