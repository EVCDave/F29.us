<?php
declare(strict_types=1);

define('ROOT_PATH',   __DIR__);
define('APP_PATH',    ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH',ROOT_PATH . '/storage');
define('DB_PATH',     ROOT_PATH . '/database');

// Composer vendor autoloader (required for endroid/qr-code and any future packages)
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
}

// Load .env if present
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Strip surrounding quotes
        if (preg_match('/^(["\']).*\1$/', $value)) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

$_ENV['APP_ENV'] ??= 'production';

date_default_timezone_set('UTC');

// Ensure the log directory exists before PHP tries to write to it
if (!is_dir(STORAGE_PATH . '/logs')) {
    if (!mkdir(STORAGE_PATH . '/logs', 0755, true) && !is_dir(STORAGE_PATH . '/logs')) {
        $logDirError = 'Startup error: cannot create storage/logs — check parent directory permissions.';
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $logDirError . "\n");
            exit(1);
        }
        http_response_code(500);
        exit($logDirError);
    }
}

$debug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors',     '1');
ini_set('error_log',      STORAGE_PATH . '/logs/error.log');

require APP_PATH . '/Config/Validator.php';
ConfigValidator::validate();

require APP_PATH . '/Config/FeatureKeys.php';

// Core application classes
require APP_PATH . '/Database.php';
require APP_PATH . '/Router.php';
require APP_PATH . '/View.php';
require APP_PATH . '/Services/AuthService.php';
require APP_PATH . '/Services/EntitlementService.php';
require APP_PATH . '/Services/SlugService.php';
require APP_PATH . '/Services/AuditLogService.php';
require APP_PATH . '/Services/QrCodeService.php';
require APP_PATH . '/Services/RedirectService.php';
require APP_PATH . '/Services/AnalyticsService.php';
require APP_PATH . '/Services/CsrfService.php';
require APP_PATH . '/Services/LoginThrottleService.php';
require APP_PATH . '/Services/DestinationHistoryService.php';
require APP_PATH . '/Services/DomainBlocklistService.php';
require APP_PATH . '/Services/UserService.php';
require APP_PATH . '/Services/MailerService.php';
require APP_PATH . '/Services/NotificationService.php';
require APP_PATH . '/Services/EmailVerificationService.php';
require APP_PATH . '/Services/PasswordResetService.php';
require APP_PATH . '/Services/QrQuotaService.php';
require APP_PATH . '/Services/QrStyleService.php';
require APP_PATH . '/Services/StaticQrPayloadService.php';
require APP_PATH . '/Services/StaticQrLogoService.php';
require APP_PATH . '/Services/StripeService.php';
require APP_PATH . '/Services/StripeWebhookService.php';
require APP_PATH . '/Services/BillingStatusService.php';

// Establish database connection
$dbConfig = require CONFIG_PATH . '/database.php';
Database::connect($dbConfig);

// Start session and apply security headers for web requests (not CLI)
if (PHP_SAPI !== 'cli') {
    AuthService::start();

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self' https://checkout.stripe.com; object-src 'none'");
}

/**
 * Send a redirect response and halt execution.
 */
function redirect(string $url): never
{
    header('Location: ' . $url, true, 302);
    exit;
}
