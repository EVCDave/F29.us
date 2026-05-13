<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be run from the command line.\n");
}

/**
 * Housekeeping script — delete stale rows from operational tables.
 *
 * Usage:
 *   php cleanup.php
 *
 * Suggested cron (daily at 03:00):
 *   0 3 * * * php /path/to/cleanup.php >> /path/to/storage/logs/cleanup.log 2>&1
 */

require __DIR__ . '/bootstrap.php';

$retentionDays = 90;

try {
    $deleted = LoginThrottleService::deleteOlderThan($retentionDays);
    echo date('Y-m-d H:i:s') . "  login_attempts: deleted {$deleted} row(s) older than {$retentionDays} days.\n";
} catch (Throwable $e) {
    fwrite(STDERR, date('Y-m-d H:i:s') . "  ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
