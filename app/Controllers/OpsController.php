<?php
declare(strict_types=1);

class OpsController
{
    public function index(array $params = []): void
    {
        $this->requireAdmin();

        $checks = $this->runChecks();

        View::render('admin/ops', [
            'pageTitle' => 'Admin: Operations — f29.us Dynamic QR',
            'checks'    => $checks,
        ]);
    }

    private function runChecks(): array
    {
        $c = [];

        // ── Environment ───────────────────────────────────────────────────────
        $c['app_env']    = $_ENV['APP_ENV']  ?? 'unknown';
        $c['app_url']    = $_ENV['APP_URL']  ?? '';
        $c['debug_mode'] = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

        // ── Runtime ───────────────────────────────────────────────────────────
        $c['php_version']     = PHP_VERSION;
        $c['gd_loaded']       = extension_loaded('gd');
        $c['mbstring_loaded'] = extension_loaded('mbstring');

        // ── Mail ─────────────────────────────────────────────────────────────
        $c['mail_enabled']       = MailerService::isEnabled();
        $c['mail_smtp_host']     = $_ENV['MAIL_SMTP_HOST']        ?? '';
        $c['mail_from_address']  = $_ENV['MAIL_FROM_ADDRESS']     ?? '';
        $c['mail_support_address'] = $_ENV['MAIL_SUPPORT_ADDRESS'] ?? $_ENV['SUPPORT_EMAIL'] ?? '';
        $c['mail_admin_address'] = trim($_ENV['MAIL_ADMIN_ADDRESS'] ?? '');
        $c['phpmailer_ok']       = file_exists(ROOT_PATH . '/vendor/PHPMailer/PHPMailer.php');

        // ── Filesystem ────────────────────────────────────────────────────────
        $c['vendor_ok']         = file_exists(ROOT_PATH . '/vendor/autoload.php');
        $logsPath               = STORAGE_PATH . '/logs';
        $c['logs_dir_exists']   = is_dir($logsPath);
        $c['logs_dir_writable'] = is_dir($logsPath) && is_writable($logsPath);

        // ── Migrations ────────────────────────────────────────────────────────
        $migrationFiles        = glob(DB_PATH . '/migrations/*.php') ?: [];
        $c['migration_count']  = count($migrationFiles);
        sort($migrationFiles);
        $c['latest_migration'] = !empty($migrationFiles)
            ? basename(end($migrationFiles), '.php')
            : 'none';

        // ── Database + counters ───────────────────────────────────────────────
        $c['db_connected'] = false;
        try {
            $pdo = Database::get();
            $pdo->query("SELECT 1");
            $c['db_connected'] = true;

            $c['total_users']        = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $c['total_qr']           = (int) $pdo->query("SELECT COUNT(*) FROM qr_codes")->fetchColumn();
            $c['total_links_active'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM short_links WHERE status = 'active'"
            )->fetchColumn();
            $c['active_subs']        = (int) $pdo->query(
                "SELECT COUNT(*) FROM user_subscriptions WHERE status = 'active'"
            )->fetchColumn();
            $c['pending_requests']   = (int) $pdo->query(
                "SELECT COUNT(*) FROM subscription_change_requests WHERE status = 'pending'"
            )->fetchColumn();

            $cutoff = gmdate('Y-m-d H:i:s', strtotime('-24 hours'));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE attempted_at >= ?");
            $stmt->execute([$cutoff]);
            $c['login_attempts_24h'] = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM login_attempts WHERE attempted_at >= ? AND success_flag = 0"
            );
            $stmt->execute([$cutoff]);
            $c['login_failures_24h'] = (int) $stmt->fetchColumn();
        } catch (Throwable) {
            // db_connected stays false; numeric checks remain absent
        }

        return $c;
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
