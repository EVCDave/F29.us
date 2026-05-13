<?php
declare(strict_types=1);

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

$deleted = LoginThrottleService::deleteOlderThan($retentionDays);

echo date('Y-m-d H:i:s') . "  login_attempts: deleted {$deleted} row(s) older than {$retentionDays} days.\n";
