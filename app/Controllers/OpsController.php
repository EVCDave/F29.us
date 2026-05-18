<?php
declare(strict_types=1);

class OpsController
{
    public function index(array $params = []): void
    {
        $this->requireAdmin();

        $checks = $this->runChecks();

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $adminUser  = AuthService::currentUser();
        $adminEmail = $adminUser['email'] ?? '';

        View::render('admin/ops', [
            'pageTitle'  => 'Admin: Operations — F29 QR Codes System',
            'checks'     => $checks,
            'flash'      => $flash,
            'adminEmail' => $adminEmail,
        ]);
    }

    public function sendTestEmail(array $params = []): void
    {
        CsrfService::requireValid();
        $this->requireAdmin();

        $recipient = trim($_POST['recipient_email'] ?? '');

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'Please enter a valid recipient email address.'];
            redirect('/admin/ops');
        }

        $subject  = 'f29.us test email';
        $bodyHtml = '<p>This is a test email from f29.us Dynamic QR.</p>'
                  . '<p>If you received this message, SMTP delivery is working.</p>';
        $bodyText = "This is a test email from f29.us Dynamic QR.\n\n"
                  . "If you received this message, SMTP delivery is working.";

        $sent = MailerService::send($recipient, $recipient, $subject, $bodyHtml, $bodyText);

        if ($sent) {
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Test email sent to ' . $recipient . '.'];
        } else {
            $_SESSION['flash'] = ['type' => 'error',
                'text' => 'Test email could not be sent. Check storage/logs/error.log for details.'];
        }

        redirect('/admin/ops');
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

        // ── Stripe ────────────────────────────────────────────────────────────
        $c['stripe_enabled']              = StripeService::isEnabled();
        $c['stripe_mode']                 = StripeService::mode();
        $c['stripe_secret_set']           = trim($_ENV['STRIPE_SECRET_KEY']      ?? '') !== '';
        $c['stripe_publishable_key_set']  = trim($_ENV['STRIPE_PUBLISHABLE_KEY']  ?? '') !== '';
        $c['stripe_webhook_set']          = trim($_ENV['STRIPE_WEBHOOK_SECRET']   ?? '') !== '';
        $c['stripe_sdk_ok']               = StripeService::clientReady();

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

            // ── Stripe DB checks ─────────────────────────────────────────────
            $stripeCurrentMode = StripeService::mode();
            $stripeOtherMode   = $stripeCurrentMode === 'test' ? 'live' : 'test';
            $c['stripe_other_mode'] = $stripeOtherMode;

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM plan_billing_prices
                  WHERE provider = 'stripe' AND provider_mode = ? AND is_active = 1"
            );
            $stmt->execute([$stripeCurrentMode]);
            $c['stripe_active_prices_current'] = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM plan_billing_prices
                  WHERE provider = 'stripe' AND provider_mode = ? AND is_active = 1"
            );
            $stmt->execute([$stripeOtherMode]);
            $c['stripe_active_prices_other'] = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT p.display_name FROM plans p
                  WHERE p.is_active = 1
                    AND (p.monthly_price_cents > 0 OR p.yearly_price_cents > 0)
                    AND NOT EXISTS (
                        SELECT 1 FROM plan_billing_prices pbp
                         WHERE pbp.plan_id = p.id
                           AND pbp.provider = 'stripe'
                           AND pbp.provider_mode = ?
                           AND pbp.is_active = 1
                    )
                  ORDER BY p.sort_order, p.display_name"
            );
            $stmt->execute([$stripeCurrentMode]);
            $c['stripe_plans_missing_prices'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Webhook table — tolerate absence if migration not yet run
            try {
                $c['stripe_webhook_total'] = (int) $pdo->query(
                    "SELECT COUNT(*) FROM stripe_webhook_events"
                )->fetchColumn();

                $c['stripe_latest_webhook'] = $pdo->query(
                    "SELECT MAX(created_at) FROM stripe_webhook_events
                      WHERE processing_status = 'processed'"
                )->fetchColumn() ?: null;

                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM stripe_webhook_events
                      WHERE processing_status = 'failed' AND created_at >= ?"
                );
                $stmt->execute([$cutoff]);
                $c['stripe_failed_webhooks_24h'] = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM stripe_webhook_events
                      WHERE processing_status = 'ignored' AND created_at >= ?"
                );
                $stmt->execute([$cutoff]);
                $c['stripe_ignored_webhooks_24h'] = (int) $stmt->fetchColumn();
            } catch (Throwable) {
                $c['stripe_webhook_total']        = null;
                $c['stripe_latest_webhook']       = null;
                $c['stripe_failed_webhooks_24h']  = null;
                $c['stripe_ignored_webhooks_24h'] = null;
            }

            // ── Subscription billing-state counts ─────────────────────────────
            try {
                $row = $pdo->query("
                    SELECT
                        SUM(CASE WHEN billing_status = 'active'    THEN 1 ELSE 0 END) AS bs_active,
                        SUM(CASE WHEN billing_status = 'trialing'  THEN 1 ELSE 0 END) AS bs_trialing,
                        SUM(CASE WHEN billing_status = 'past_due'  THEN 1 ELSE 0 END) AS bs_past_due,
                        SUM(CASE WHEN billing_status = 'unpaid'    THEN 1 ELSE 0 END) AS bs_unpaid,
                        SUM(CASE WHEN billing_status = 'incomplete' THEN 1 ELSE 0 END) AS bs_incomplete,
                        SUM(CASE WHEN cancel_at_period_end = 1
                                  AND billing_status NOT IN ('canceled','unpaid','incomplete')
                             THEN 1 ELSE 0 END)                                       AS bs_cancel_soon
                      FROM user_subscriptions
                     WHERE status = 'active'
                ")->fetch(PDO::FETCH_ASSOC);

                $c['sub_bs_active']      = (int) ($row['bs_active']      ?? 0);
                $c['sub_bs_trialing']    = (int) ($row['bs_trialing']    ?? 0);
                $c['sub_bs_past_due']    = (int) ($row['bs_past_due']    ?? 0);
                $c['sub_bs_unpaid']      = (int) ($row['bs_unpaid']      ?? 0);
                $c['sub_bs_incomplete']  = (int) ($row['bs_incomplete']  ?? 0);
                $c['sub_bs_cancel_soon'] = (int) ($row['bs_cancel_soon'] ?? 0);
            } catch (Throwable) {
                $c['sub_bs_active']      = null;
                $c['sub_bs_trialing']    = null;
                $c['sub_bs_past_due']    = null;
                $c['sub_bs_unpaid']      = null;
                $c['sub_bs_incomplete']  = null;
                $c['sub_bs_cancel_soon'] = null;
            }
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
