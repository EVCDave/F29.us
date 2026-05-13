<?php
$ok   = '<span style="color:#166534;font-weight:500">&#10003; OK</span>';
$warn = static function (string $msg): string {
    return '<span style="color:#92400e;font-weight:500">&#9888; ' . View::e($msg) . '</span>';
};
$fail = static function (string $msg): string {
    return '<span style="color:#991b1b;font-weight:500">&#10007; ' . View::e($msg) . '</span>';
};
?>
<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1rem">
    <h1>Operations</h1>
    <a href="/admin" style="color:#666;font-size:0.9rem">&larr; Admin</a>
</div>
<p style="color:#666;margin-bottom:2rem">System health snapshot. Refresh the page to recheck.</p>

<!-- ── Environment ────────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.6rem">Environment</h2>
<table style="max-width:560px;margin-bottom:2rem;font-size:0.9rem">
    <tr>
        <th style="width:200px">APP_ENV</th>
        <td><?= View::e($checks['app_env']) ?></td>
    </tr>
    <tr>
        <th>APP_URL</th>
        <td><?= View::e($checks['app_url']) ?></td>
    </tr>
    <tr>
        <th>Debug Mode</th>
        <td><?= $checks['debug_mode']
            ? $warn('ON — debug mode is enabled')
            : $ok ?></td>
    </tr>
    <tr>
        <th>PHP Version</th>
        <td><?= View::e($checks['php_version']) ?></td>
    </tr>
</table>

<!-- ── Extensions ────────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.6rem">PHP Extensions</h2>
<table style="max-width:560px;margin-bottom:2rem;font-size:0.9rem">
    <tr>
        <th style="width:200px">GD (PNG generation)</th>
        <td><?= $checks['gd_loaded'] ? $ok : $warn('not loaded — PNG downloads will fail') ?></td>
    </tr>
    <tr>
        <th>mbstring</th>
        <td><?= $checks['mbstring_loaded'] ? $ok : $fail('not loaded — required') ?></td>
    </tr>
</table>

<!-- ── Filesystem ─────────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.6rem">Filesystem</h2>
<table style="max-width:560px;margin-bottom:2rem;font-size:0.9rem">
    <tr>
        <th style="width:200px">vendor/autoload.php</th>
        <td><?= $checks['vendor_ok'] ? $ok : $fail('missing — run composer install') ?></td>
    </tr>
    <tr>
        <th>storage/logs directory</th>
        <td><?= $checks['logs_dir_exists'] ? $ok : $warn('does not exist') ?></td>
    </tr>
    <tr>
        <th>storage/logs writable</th>
        <td><?= $checks['logs_dir_writable'] ? $ok : $warn('not writable — error logging will fail') ?></td>
    </tr>
</table>

<!-- ── Migrations ─────────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.6rem">Migrations</h2>
<table style="max-width:560px;margin-bottom:2rem;font-size:0.9rem">
    <tr>
        <th style="width:200px">Migration files</th>
        <td><?= (int) $checks['migration_count'] ?></td>
    </tr>
    <tr>
        <th>Latest migration</th>
        <td style="font-family:monospace;font-size:0.85rem"><?= View::e($checks['latest_migration']) ?></td>
    </tr>
</table>

<!-- ── Database ───────────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.6rem">Database</h2>
<table style="max-width:560px;margin-bottom:2rem;font-size:0.9rem">
    <tr>
        <th style="width:200px">Connection</th>
        <td><?= $checks['db_connected'] ? $ok : $fail('cannot connect — check DB credentials') ?></td>
    </tr>
    <?php if ($checks['db_connected']): ?>
    <tr>
        <th>Total users</th>
        <td><?= (int) $checks['total_users'] ?></td>
    </tr>
    <tr>
        <th>Total QR codes</th>
        <td><?= (int) $checks['total_qr'] ?></td>
    </tr>
    <tr>
        <th>Active short links</th>
        <td><?= (int) $checks['total_links_active'] ?></td>
    </tr>
    <tr>
        <th>Active subscriptions</th>
        <td><?= (int) $checks['active_subs'] ?></td>
    </tr>
    <tr>
        <th>Pending requests</th>
        <td>
            <?= (int) $checks['pending_requests'] ?>
            <?php if ($checks['pending_requests'] > 0): ?>
            <a href="/admin/subscription-requests" style="font-size:0.82rem;margin-left:0.5rem">View</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endif; ?>
</table>

<!-- ── Login Activity ─────────────────────────────────────────────────────── -->
<?php if ($checks['db_connected']): ?>
<h2 style="margin-bottom:0.6rem">Login Activity (last 24 h)</h2>
<table style="max-width:560px;margin-bottom:2rem;font-size:0.9rem">
    <tr>
        <th style="width:200px">Total attempts</th>
        <td><?= (int) $checks['login_attempts_24h'] ?></td>
    </tr>
    <tr>
        <th>Failed attempts</th>
        <td>
            <?php $failures = (int) $checks['login_failures_24h']; ?>
            <?= $failures > 20
                ? $warn($failures . ' — elevated failure count')
                : View::e((string) $failures) ?>
        </td>
    </tr>
</table>
<?php endif; ?>
