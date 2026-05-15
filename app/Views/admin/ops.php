<?php
$ok   = '<span class="ops-ok">&#10003; OK</span>';
$warn = static function (string $msg): string {
    return '<span class="ops-warn">&#9888; ' . View::e($msg) . '</span>';
};
$fail = static function (string $msg): string {
    return '<span class="ops-fail">&#10007; ' . View::e($msg) . '</span>';
};
?>
<div class="page-header">
    <h1>Operations</h1>
    <a href="/admin" class="back-link">&larr; Admin</a>
</div>
<p class="text-muted-3 mb-8">System health snapshot. Refresh the page to recheck.</p>

<!-- ── Environment ────────────────────────────────────────────────────────── -->
<h2 class="mb-3">Environment</h2>
<table class="mw-560 mb-8 text-base">
    <tr>
        <th class="col-200">APP_ENV</th>
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
<h2 class="mb-3">PHP Extensions</h2>
<table class="mw-560 mb-8 text-base">
    <tr>
        <th class="col-200">GD (PNG generation)</th>
        <td><?= $checks['gd_loaded'] ? $ok : $warn('not loaded — PNG downloads will fail') ?></td>
    </tr>
    <tr>
        <th>mbstring</th>
        <td><?= $checks['mbstring_loaded'] ? $ok : $fail('not loaded — required') ?></td>
    </tr>
</table>

<!-- ── Mail ───────────────────────────────────────────────────────────────── -->
<h2 class="mb-3">Mail Configuration</h2>
<table class="mw-560 mb-8 text-base">
    <tr>
        <th class="col-200">MAIL_ENABLED</th>
        <td><?= $checks['mail_enabled']
            ? $ok
            : $warn('disabled — transactional emails will not be sent') ?></td>
    </tr>
    <tr>
        <th>PHPMailer present</th>
        <td><?= $checks['phpmailer_ok'] ? $ok : $fail('missing — vendor/PHPMailer/PHPMailer.php not found') ?></td>
    </tr>
    <?php if ($checks['mail_enabled']): ?>
    <tr>
        <th>MAIL_SMTP_HOST</th>
        <td><?= $checks['mail_smtp_host'] !== ''
            ? View::e($checks['mail_smtp_host'])
            : $fail('not set') ?></td>
    </tr>
    <tr>
        <th>MAIL_FROM_ADDRESS</th>
        <td><?= $checks['mail_from_address'] !== ''
            ? View::e($checks['mail_from_address'])
            : $fail('not set') ?></td>
    </tr>
    <tr>
        <th>MAIL_SUPPORT_ADDRESS</th>
        <td><?= View::e($checks['mail_support_address'] !== '' ? $checks['mail_support_address'] : '(using SUPPORT_EMAIL fallback)') ?></td>
    </tr>
    <tr>
        <th>MAIL_ADMIN_ADDRESS</th>
        <td><?= $checks['mail_admin_address'] !== ''
            ? View::e($checks['mail_admin_address'])
            : '<span class="text-muted">not set — admin notifications disabled</span>' ?></td>
    </tr>
    <?php endif; ?>
</table>

<!-- ── Filesystem ─────────────────────────────────────────────────────────── -->
<h2 class="mb-3">Filesystem</h2>
<table class="mw-560 mb-8 text-base">
    <tr>
        <th class="col-200">vendor/autoload.php</th>
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
<h2 class="mb-3">Migrations</h2>
<table class="mw-560 mb-8 text-base">
    <tr>
        <th class="col-200">Migration files</th>
        <td><?= (int) $checks['migration_count'] ?></td>
    </tr>
    <tr>
        <th>Latest migration</th>
        <td class="monospace text-sm"><?= View::e($checks['latest_migration']) ?></td>
    </tr>
</table>

<!-- ── Database ───────────────────────────────────────────────────────────── -->
<h2 class="mb-3">Database</h2>
<table class="mw-560 mb-8 text-base">
    <tr>
        <th class="col-200">Connection</th>
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
            <a href="/admin/subscription-requests" class="text-sm ml-2">View</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endif; ?>
</table>

<!-- ── Login Activity ─────────────────────────────────────────────────────── -->
<?php if ($checks['db_connected']): ?>
<h2 class="mb-3">Login Activity (last 24 h)</h2>
<table class="mw-560 mb-8 text-base">
    <tr>
        <th class="col-200">Total attempts</th>
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
