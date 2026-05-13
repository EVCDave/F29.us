<?php
$isFreePlan   = $activeSub && $activeSub['plan_internal_name'] === 'free_v1';
$isPlanLegacy = $activeSub && (bool) $activeSub['plan_is_legacy'];

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

$statusHistoryMessages = [
    'approved' => 'Approved — your subscription was updated.',
    'denied'   => 'Denied — your current subscription was not changed.',
    'canceled' => 'Canceled — this request was canceled and no subscription change was made.',
];
$statusHistoryColors = [
    'approved' => 'color:#166534',
    'denied'   => 'color:#991b1b',
    'canceled' => 'color:#6b7280',
];
?>

<h1 style="margin-bottom:1.5rem">My Subscription</h1>

<?php if ($flash): ?>
<div class="notice" style="display:block;margin-bottom:1.5rem;
    <?= $flash['type'] === 'error'   ? 'background:#fef2f2;border-color:#fca5a5;color:#991b1b;' : '' ?>
    <?= $flash['type'] === 'success' ? 'background:#f0fdf4;border-color:#86efac;color:#166534;' : '' ?>
    <?= $flash['type'] === 'info'    ? 'background:#eff6ff;border-color:#93c5fd;color:#1e40af;' : '' ?>">
    <?= View::e($flash['text']) ?>
</div>
<?php endif; ?>

<!-- ── Current Plan ────────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.75rem">Current Plan</h2>

<?php if ($activeSub): ?>

<?php if ($isPlanLegacy): ?>
<div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:4px;padding:0.65rem 0.9rem;
            max-width:520px;margin-bottom:1rem;font-size:0.88rem;color:#92400e">
    You are on a legacy version of this plan. It is no longer publicly available. Your access continues
    as long as your subscription remains active.
</div>
<?php elseif ($isFreePlan): ?>
<p style="color:#555;margin-bottom:0.75rem;font-size:0.92rem;max-width:520px">
    You are on the Free plan. To access more features, submit a request for another plan using the
    comparison table below.
</p>
<?php else: ?>
<p style="color:#555;margin-bottom:0.75rem;font-size:0.92rem;max-width:520px">
    You are on the <strong><?= View::e($activeSub['plan_display_name']) ?></strong> plan.
    Billing is not yet automated — plan changes are reviewed manually by our team.
</p>
<?php endif; ?>

<table style="max-width:480px;margin-bottom:2rem">
    <tr>
        <th style="width:160px">Plan</th>
        <td>
            <strong><?= View::e($activeSub['plan_display_name']) ?></strong>
            <span style="color:#9ca3af;font-size:0.85rem">(<?= View::e($activeSub['plan_internal_name']) ?>)</span>
            <?php if ($isPlanLegacy): ?>
            <span style="font-size:0.72rem;background:#fef3c7;color:#92400e;border:1px solid #f59e0b;
                         padding:0.1rem 0.4rem;border-radius:3px;margin-left:0.4rem">legacy</span>
            <?php endif; ?>
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

<?php else: ?>
<p style="color:#888;margin-bottom:2rem">You do not have an active subscription.</p>
<?php endif; ?>

<!-- ── Usage Summary ──────────────────────────────────────────────────────── -->
<?php
$maxQr           = $entitlements['max_qr_codes']             ?? null;
$analyticsRetain = $entitlements['analytics_retention_days'] ?? null;
$canSvg          = $entitlements['can_export_svg']           ?? null;
$canCustomSlug   = $entitlements['can_use_custom_slug']      ?? null;
$showUsage       = $maxQr !== null || $analyticsRetain !== null || $canSvg !== null || $canCustomSlug !== null;
?>
<?php if ($showUsage): ?>
<h2 style="margin-bottom:0.75rem">Current Usage &amp; Limits</h2>
<table style="max-width:380px;margin-bottom:2rem;font-size:0.9rem">
    <?php if ($maxQr !== null): ?>
    <tr>
        <th style="width:200px;font-weight:500;color:#374151">QR Codes</th>
        <td>
            <?= (int) $currentQrCount ?> / <?= View::e((string) $maxQr) ?>
            <?php if ((int) $currentQrCount >= (int) $maxQr): ?>
            <span style="color:#991b1b;font-size:0.82rem;margin-left:0.35rem">limit reached</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endif; ?>
    <?php if ($analyticsRetain !== null): ?>
    <tr>
        <th style="font-weight:500;color:#374151">Analytics Retention</th>
        <td><?= View::e((string) $analyticsRetain) ?> days</td>
    </tr>
    <?php endif; ?>
    <?php if ($canCustomSlug !== null): ?>
    <tr>
        <th style="font-weight:500;color:#374151">Custom Short Links</th>
        <td><?= $canCustomSlug ? '<span style="color:#166534">&#10003; available</span>' : '<span style="color:#9ca3af">not available</span>' ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($canSvg !== null): ?>
    <tr>
        <th style="font-weight:500;color:#374151">SVG Export</th>
        <td><?= $canSvg ? '<span style="color:#166534">&#10003; available</span>' : '<span style="color:#9ca3af">not available</span>' ?></td>
    </tr>
    <?php endif; ?>
</table>
<?php endif; ?>

<!-- ── Pending Requests ───────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.75rem">Pending Plan-Change Requests</h2>

<?php if (empty($pendingRequests)): ?>
<p style="color:#888;font-size:0.9rem;margin-bottom:2rem">You do not have any pending plan-change requests.</p>
<?php else: ?>
<p style="color:#555;font-size:0.88rem;margin-bottom:0.75rem;max-width:520px">
    The following requests are waiting for admin review. You will be notified once a decision is made.
    Canceling a request does not affect your current subscription.
</p>
<table style="max-width:620px;margin-bottom:2rem">
    <thead>
        <tr>
            <th>Requested Plan</th>
            <th style="width:140px">Current Plan at Request</th>
            <th style="width:100px">Submitted</th>
            <th style="width:90px"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($pendingRequests as $req): ?>
    <tr>
        <td>
            <strong><?= View::e($req['requested_plan_name']) ?></strong>
            <span style="color:#9ca3af;font-size:0.83rem">(<?= View::e($req['requested_plan_internal']) ?>)</span>
        </td>
        <td style="font-size:0.85rem;color:#6b7280">
            <?= $req['current_plan_name'] ? View::e($req['current_plan_name']) : '<span style="color:#9ca3af">none</span>' ?>
        </td>
        <td style="font-size:0.85rem;color:#6b7280"><?= View::e(substr($req['requested_at'], 0, 10)) ?></td>
        <td>
            <form method="post" action="/account/subscription/request-cancel"
                  onsubmit="return confirm('Cancel this plan-change request?\nYour current subscription will not be affected.')">
                <?= CsrfService::field() ?>
                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                <button type="submit" class="btn btn-secondary"
                        style="padding:0.2rem 0.6rem;font-size:0.8rem">Cancel Request</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- ── Request History ────────────────────────────────────────────────────── -->
<?php if (!empty($requestHistory)): ?>
<h2 style="margin-bottom:0.75rem">Recent Request History</h2>
<table style="max-width:680px;margin-bottom:2rem;font-size:0.88rem">
    <thead>
        <tr>
            <th>Requested Plan</th>
            <th style="width:100px">Status</th>
            <th style="width:100px">Submitted</th>
            <th style="width:100px">Reviewed</th>
            <th>Outcome</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($requestHistory as $h): ?>
    <tr>
        <td>
            <?= View::e($h['requested_plan_name']) ?>
            <span style="color:#9ca3af;font-size:0.82rem">(<?= View::e($h['requested_plan_internal']) ?>)</span>
        </td>
        <td style="<?= $statusHistoryColors[$h['status']] ?? '' ?>;font-weight:500">
            <?= View::e($h['status']) ?>
        </td>
        <td style="color:#6b7280"><?= View::e(substr($h['requested_at'], 0, 10)) ?></td>
        <td style="color:#6b7280">
            <?= $h['reviewed_at'] ? View::e(substr($h['reviewed_at'], 0, 10)) : '<span style="color:#d1d5db">—</span>' ?>
        </td>
        <td style="color:#6b7280;font-size:0.85rem">
            <?php if (!empty($h['note'])): ?>
                <?= View::e($h['note']) ?>
            <?php elseif (isset($statusHistoryMessages[$h['status']])): ?>
                <?= View::e($statusHistoryMessages[$h['status']]) ?>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- ── Available Plans ────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.75rem">Available Plans</h2>

<?php if (empty($plans)): ?>
<p style="color:#888;margin-bottom:2rem">No plans are currently available.</p>
<?php else: ?>

<div style="overflow-x:auto;margin-bottom:0.5rem">
<table style="min-width:520px;margin-bottom:0">
    <thead>
        <tr>
            <th style="width:170px;font-size:0.82rem;color:#6b7280">Feature</th>
            <?php foreach ($plans as $p): ?>
            <th style="text-align:center;min-width:120px">
                <?= View::e($p['display_name']) ?>
                <?php if ($currentPlanId === (int) $p['id']): ?>
                <div style="font-size:0.7rem;color:#166534;font-weight:500;margin-top:0.1rem">&#10003; current</div>
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
        <td style="text-align:center;padding-top:0.9rem;padding-bottom:0.5rem">
            <?php if ($isCurrent): ?>
                <span class="btn-disabled" style="font-size:0.8rem;padding:0.3rem 0.75rem">Current Plan</span>
            <?php elseif ($isPending): ?>
                <span class="btn-disabled" style="font-size:0.8rem;padding:0.3rem 0.75rem">Request Pending</span>
            <?php else: ?>
                <form method="post" action="/account/subscription/change">
                    <?= CsrfService::field() ?>
                    <input type="hidden" name="plan_id" value="<?= $pid ?>">
                    <button type="submit" class="btn" style="font-size:0.8rem;padding:0.3rem 0.75rem">
                        <?= $isFree ? 'Switch to Free' : 'Request Review' ?>
                    </button>
                </form>
            <?php endif; ?>
        </td>
        <?php endforeach; ?>
    </tr>
    </tbody>
</table>
</div>

<p style="font-size:0.82rem;color:#6b7280;margin-top:0.5rem">
    Switching to Free takes effect immediately. Requests for other plans are reviewed manually.
    Billing is not automated yet — no charges apply until explicitly confirmed.
</p>

<?php endif; ?>
