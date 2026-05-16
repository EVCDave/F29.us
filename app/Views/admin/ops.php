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

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?> mb-6"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

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
<p class="text-sm text-muted mb-3">
    <em>Configured</em> means the app has enough settings to attempt delivery.
    Use Send Test Email below to confirm SMTP delivery.
</p>
<table class="mw-560 mb-4 text-base">
    <tr>
        <th class="col-200">MAIL_ENABLED</th>
        <td><?= $checks['mail_enabled']
            ? '<span class="ops-ok">&#10003; configured</span>'
            : $warn('disabled — transactional emails will not be sent') ?></td>
    </tr>
    <tr>
        <th>PHPMailer present</th>
        <td><?= $checks['phpmailer_ok']
            ? '<span class="ops-ok">&#10003; present</span>'
            : $fail('missing — vendor/PHPMailer/PHPMailer.php not found') ?></td>
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

<!-- ── Send Test Email ─────────────────────────────────────────────────────── -->
<div class="card-note mw-520 mb-8">
    <p class="fw-medium mb-3">Send Test Email</p>
    <form method="post" action="/admin/ops/send-test-email">
        <?= CsrfService::field() ?>
        <div class="form-group mb-3">
            <label for="recipient_email">Recipient</label>
            <input
                type="email"
                id="recipient_email"
                name="recipient_email"
                value="<?= View::e($adminEmail) ?>"
                required
                autocomplete="off"
            >
        </div>
        <button type="submit" class="btn">Send Test Email</button>
    </form>
    <p class="text-2xs text-muted-2 mt-3">
        This sends a real email using the current SMTP configuration.
        Delivery failures are logged server-side.
    </p>
</div>

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

<!-- ── Stripe ─────────────────────────────────────────────────────────────── -->
<h2 class="mb-3">Stripe Configuration</h2>
<p class="text-sm text-muted mb-3">
    <em>Configured</em> means the value is set. No key values are displayed.
    Enable <code>STRIPE_ENABLED=true</code> in <code>.env</code> to activate billing.
</p>
<table class="mw-560 mb-4 text-base">
    <tr>
        <th class="col-200">STRIPE_ENABLED</th>
        <td><?= $checks['stripe_enabled']
            ? '<span class="ops-ok">&#10003; enabled</span>'
            : '<span class="text-muted">disabled</span>' ?></td>
    </tr>
    <tr>
        <th>STRIPE_MODE</th>
        <td>
            <?php $stripeMode = View::e($checks['stripe_mode']); ?>
            <?= $checks['stripe_mode'] === 'live'
                ? $warn('live — real charges will be made')
                : View::e($checks['stripe_mode']) ?>
        </td>
    </tr>
    <tr>
        <th>STRIPE_SECRET_KEY</th>
        <td><?= $checks['stripe_secret_set']
            ? '<span class="ops-ok">&#10003; configured</span>'
            : $warn('not set') ?></td>
    </tr>
    <tr>
        <th>STRIPE_PUBLISHABLE_KEY</th>
        <td><?= $checks['stripe_publishable_key_set']
            ? '<span class="ops-ok">&#10003; configured</span>'
            : $warn('not set') ?></td>
    </tr>
    <tr>
        <th>STRIPE_WEBHOOK_SECRET</th>
        <td><?= $checks['stripe_webhook_set']
            ? '<span class="ops-ok">&#10003; configured</span>'
            : $warn('not set') ?></td>
    </tr>
    <tr>
        <th>Stripe SDK</th>
        <td><?= $checks['stripe_sdk_ok']
            ? '<span class="ops-ok">&#10003; present</span>'
            : $warn('not found — run: composer require stripe/stripe-php') ?></td>
    </tr>
    <?php if ($checks['db_connected']): ?>
    <tr>
        <th>Active Stripe prices</th>
        <td><?= (int) ($checks['stripe_active_prices'] ?? 0) ?></td>
    </tr>
    <tr>
        <th>Paid plans missing<br>active Stripe price</th>
        <td>
            <?php $missing = $checks['stripe_plans_missing_prices'] ?? []; ?>
            <?php if (empty($missing)): ?>
                <span class="ops-ok">&#10003; none</span>
            <?php else: ?>
                <?= $warn(implode(', ', array_map('htmlspecialchars', $missing))) ?>
            <?php endif; ?>
        </td>
    </tr>
    <?php if ($checks['stripe_webhook_total'] !== null): ?>
    <tr>
        <th>Webhook events (total)</th>
        <td><?= (int) $checks['stripe_webhook_total'] ?></td>
    </tr>
    <tr>
        <th>Latest processed</th>
        <td class="monospace text-sm">
            <?= $checks['stripe_latest_webhook'] !== null
                ? View::e((string) $checks['stripe_latest_webhook'])
                : '<span class="text-muted">none yet</span>' ?>
        </td>
    </tr>
    <tr>
        <th>Failed webhooks (24 h)</th>
        <td>
            <?php $wfail = (int) ($checks['stripe_failed_webhooks_24h'] ?? 0); ?>
            <?= $wfail > 0
                ? $warn($wfail . ' failed')
                : '<span class="ops-ok">&#10003; 0</span>' ?>
        </td>
    </tr>
    <tr>
        <th>Ignored webhooks (24 h)</th>
        <td><?= (int) ($checks['stripe_ignored_webhooks_24h'] ?? 0) ?></td>
    </tr>
    <?php else: ?>
    <tr>
        <th>Webhook events table</th>
        <td><?= $fail('table not found — run migration 029') ?></td>
    </tr>
    <?php endif; ?>
    <?php endif; ?>
</table>

<!-- ── Subscription Billing State ────────────────────────────────────────── -->
<?php if ($checks['db_connected'] && $checks['sub_bs_active'] !== null): ?>
<h2 class="mb-3">Subscription Billing State</h2>
<table class="mw-560 mb-8 text-base">
    <tr>
        <th class="col-200">Active</th>
        <td><?= (int) $checks['sub_bs_active'] ?></td>
    </tr>
    <tr>
        <th>Trialing</th>
        <td><?= (int) $checks['sub_bs_trialing'] ?></td>
    </tr>
    <tr>
        <th>Past due</th>
        <td>
            <?php $pd = (int) $checks['sub_bs_past_due']; ?>
            <?= $pd > 0 ? $warn($pd . ' past due') : View::e((string) $pd) ?>
        </td>
    </tr>
    <tr>
        <th>Unpaid</th>
        <td>
            <?php $un = (int) $checks['sub_bs_unpaid']; ?>
            <?= $un > 0 ? $warn($un . ' unpaid') : View::e((string) $un) ?>
        </td>
    </tr>
    <tr>
        <th>Incomplete</th>
        <td>
            <?php $ic = (int) $checks['sub_bs_incomplete']; ?>
            <?= $ic > 0 ? $warn($ic . ' incomplete') : View::e((string) $ic) ?>
        </td>
    </tr>
    <tr>
        <th>Canceling at period end</th>
        <td>
            <?php $cs = (int) $checks['sub_bs_cancel_soon']; ?>
            <?= $cs > 0
                ? '<span class="text-muted">' . $cs . '</span>'
                : View::e((string) $cs) ?>
        </td>
    </tr>
</table>
<?php endif; ?>
