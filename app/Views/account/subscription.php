<?php
$displayFeatures = [
    'max_qr_codes'             => 'QR Codes',
    'analytics_retention_days' => 'Analytics (days)',
    'can_export_png'           => 'PNG Download',
    'can_export_svg'           => 'SVG Download',
    'can_use_custom_slug'      => 'Custom Short Links',
    'can_pause_links'          => 'Pause Links',
    'can_export_analytics'     => 'Export Analytics',
];

$fv = static function (array $planFeatures, string $key): string {
    if (!isset($planFeatures[$key])) {
        return '<span style="color:#9ca3af">—</span>';
    }
    $f = $planFeatures[$key];
    if ($f['value_type'] === 'bool') {
        return $f['feature_value'] === 'true'
            ? '<span style="color:#166534">&#10003;</span>'
            : '<span style="color:#9ca3af">—</span>';
    }
    return View::e($f['feature_value']);
};
?>

<h1 style="margin-bottom:1.5rem">My Subscription</h1>

<?php if ($flash): ?>
<div class="notice" style="
    <?= $flash['type'] === 'error'   ? 'background:#fef2f2;border-color:#fca5a5;color:#991b1b;' : '' ?>
    <?= $flash['type'] === 'success' ? 'background:#f0fdf4;border-color:#86efac;color:#166534;' : '' ?>
    display:block;margin-bottom:1.5rem">
    <?= View::e($flash['text']) ?>
</div>
<?php endif; ?>

<!-- ── Current subscription ───────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.75rem">Current Plan</h2>

<?php if ($activeSub): ?>
<table style="max-width:480px;margin-bottom:2.5rem">
    <tr>
        <th style="width:160px">Plan</th>
        <td><strong><?= View::e($activeSub['plan_display_name']) ?></strong>
            <span style="color:#9ca3af;font-size:0.85rem">(<?= View::e($activeSub['plan_internal_name']) ?>)</span>
        </td>
    </tr>
    <tr>
        <th>Status</th>
        <td class="status-<?= View::e($activeSub['status']) ?>"><?= View::e($activeSub['status']) ?></td>
    </tr>
    <tr>
        <th>Billing Cycle</th>
        <td><?= View::e($activeSub['billing_cycle']) ?></td>
    </tr>
    <tr>
        <th>Started</th>
        <td><?= View::e(substr($activeSub['started_at'], 0, 10)) ?></td>
    </tr>
    <?php if ($activeSub['grandfathered_at']): ?>
    <tr>
        <th>Grandfathered</th>
        <td><?= View::e(substr($activeSub['grandfathered_at'], 0, 10)) ?></td>
    </tr>
    <?php endif; ?>
</table>

<?php if (!empty($entitlements)): ?>
<h3 style="font-size:0.95rem;font-weight:600;margin-bottom:0.5rem">Your Current Entitlements</h3>
<table style="max-width:380px;margin-bottom:2.5rem;font-size:0.88rem">
    <?php foreach ($displayFeatures as $key => $label): ?>
    <?php if (array_key_exists($key, $entitlements)): ?>
    <tr>
        <th style="width:180px;font-weight:500;color:#374151"><?= View::e($label) ?></th>
        <td>
            <?php $val = $entitlements[$key]; ?>
            <?php if (is_bool($val)): ?>
                <?= $val
                    ? '<span style="color:#166534">&#10003; yes</span>'
                    : '<span style="color:#9ca3af">—</span>' ?>
            <?php else: ?>
                <?= View::e((string) $val) ?>
            <?php endif; ?>
        </td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php else: ?>
<p style="color:#888;margin-bottom:2.5rem">You do not have an active subscription.</p>
<?php endif; ?>

<!-- ── Pending requests ───────────────────────────────────────────────────── -->
<?php if (!empty($pendingRequests)): ?>
<h2 style="margin-bottom:0.75rem">Pending Requests</h2>
<table style="max-width:580px;margin-bottom:2.5rem">
    <thead>
        <tr>
            <th>Plan Requested</th>
            <th style="width:110px">Submitted</th>
            <th style="width:80px"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($pendingRequests as $req): ?>
    <tr>
        <td>
            <strong><?= View::e($req['requested_plan_name']) ?></strong>
            <span style="color:#9ca3af;font-size:0.83rem">(<?= View::e($req['requested_plan_internal']) ?>)</span>
        </td>
        <td style="font-size:0.85rem;color:#6b7280"><?= View::e(substr($req['requested_at'], 0, 10)) ?></td>
        <td>
            <form method="post" action="/account/subscription/request-cancel"
                  onsubmit="return confirm('Cancel this request?')">
                <?= CsrfService::field() ?>
                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                <button type="submit" class="btn btn-secondary"
                        style="padding:0.2rem 0.6rem;font-size:0.8rem">Cancel</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- ── Available plans ────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.75rem">Available Plans</h2>

<?php if (empty($plans)): ?>
<p style="color:#888;margin-bottom:2rem">No plans are currently available.</p>
<?php else: ?>

<div style="overflow-x:auto;margin-bottom:0.75rem">
<table style="min-width:520px;margin-bottom:0">
    <thead>
        <tr>
            <th style="width:160px;font-size:0.82rem;color:#6b7280">Feature</th>
            <?php foreach ($plans as $p): ?>
            <th style="text-align:center;min-width:120px">
                <?= View::e($p['display_name']) ?>
                <?php if ($currentPlanId === (int) $p['id']): ?>
                <div style="font-size:0.7rem;color:#6b7280;font-weight:400">current</div>
                <?php endif; ?>
            </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($displayFeatures as $key => $label): ?>
    <tr>
        <td style="font-size:0.85rem;color:#374151"><?= View::e($label) ?></td>
        <?php foreach ($plans as $p): ?>
        <td style="text-align:center;font-size:0.88rem">
            <?= $fv($features[(int) $p['id']] ?? [], $key) ?>
        </td>
        <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
    <tr>
        <td></td>
        <?php foreach ($plans as $p): ?>
        <?php
        $pid       = (int) $p['id'];
        $isCurrent = $currentPlanId === $pid;
        $isPending = in_array($pid, $pendingPlanIds, true);
        $isFree    = $p['internal_name'] === 'free_v1';
        ?>
        <td style="text-align:center;padding-top:0.9rem;padding-bottom:0.4rem">
            <?php if ($isCurrent): ?>
                <span class="btn-disabled" style="font-size:0.8rem;padding:0.3rem 0.7rem">Current</span>
            <?php elseif ($isPending): ?>
                <span class="btn-disabled" style="font-size:0.8rem;padding:0.3rem 0.7rem">Pending</span>
            <?php else: ?>
                <form method="post" action="/account/subscription/change">
                    <?= CsrfService::field() ?>
                    <input type="hidden" name="plan_id" value="<?= $pid ?>">
                    <button type="submit" class="btn" style="font-size:0.8rem;padding:0.3rem 0.7rem">
                        <?= $isFree ? 'Switch' : 'Request' ?>
                    </button>
                </form>
            <?php endif; ?>
        </td>
        <?php endforeach; ?>
    </tr>
    </tbody>
</table>
</div>

<p style="font-size:0.82rem;color:#6b7280">
    Switching to the Free plan takes effect immediately.
    All other plans require review — submit a request and we will be in touch.
    No charges apply until explicitly confirmed.
</p>

<?php endif; ?>
