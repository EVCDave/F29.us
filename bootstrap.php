<?php
declare(strict_types=1);

define('ROOT_PATH',   __DIR__);
define('APP_PATH',    ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH',ROOT_PATH . '/storage');
define('DB_PATH',     ROOT_PATH . '/database');

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

date_default_timezone_set('UTC');

$debug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors',     '1');
ini_set('error_log',      STORAGE_PATH . '/logs/error.log');

// Core application classes
require APP_PATH . '/Database.php';
require APP_PATH . '/Router.php';
require APP_PATH . '/View.php';
require APP_PATH . '/Services/AuthService.php';
require APP_PATH . '/Services/EntitlementService.php';
require APP_PATH . '/Services/SlugService.php';

// Establish database connection
$dbConfig = require CONFIG_PATH . '/database.php';
Database::connect($dbConfig);

// Start session for web requests (not CLI)
if (PHP_SAPI !== 'cli') {
    AuthService::start();
}

/**
 * Send a redirect response and halt execution.
 */
function redirect(string $url): never
{
    header('Location: ' . $url, true, 302);
    exit;
}
