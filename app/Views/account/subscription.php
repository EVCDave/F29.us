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
    'can_customize_qr_colors'  => 'Custom QR Colors/Backgrounds',
    'can_customize_qr_module_style' => 'Custom QR module styles',
    'can_upload_qr_logo'       => 'QR Logo Upload',
    'qr_logo_max_size_kb'      => 'Logo Max Size',
    'qr_logo_max_percent'      => 'Logo Coverage',
    'max_qr_download_size_px'  => 'Max PNG download size',
];

$fv = static function (array $planFeatures, string $key): string {
    if (!isset($planFeatures[$key])) {
        return '<span class="text-faint">—</span>';
    }
    $f = $planFeatures[$key];
    if ($f['value_type'] === 'bool') {
        return $f['feature_value'] === 'true'
            ? '<span class="text-success">&#10003;</span>'
            : '<span class="text-faint">—</span>';
    }
    $v = (int) $f['feature_value'];
    if ($key === 'qr_logo_max_size_kb') {
        return $v > 0 ? View::e((string) $v) . ' KB' : '<span class="text-faint">—</span>';
    }
    if ($key === 'qr_logo_max_percent') {
        return $v > 0 ? View::e((string) $v) . '%' : '<span class="text-faint">—</span>';
    }
    if ($key === 'max_qr_download_size_px') {
        return $v > 0 ? View::e((string) $v) . 'px' : '<span class="text-faint">—</span>';
    }
    return View::e($f['feature_value']);
};

$statusHistoryMessages = [
    'approved' => 'Approved — your subscription was updated.',
    'denied'   => 'Denied — your current subscription was not changed.',
    'canceled' => 'Canceled — this request was canceled and no subscription change was made.',
];
?>

<h1 class="mb-6">My Subscription</h1>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?> mb-6"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<?php if ($checkoutStatus === 'success'): ?>
<div class="flash flash-info mb-6">
    Checkout completed. Your subscription will update after Stripe confirms payment.
    This usually takes a few seconds — refresh the page if your plan has not updated.
</div>
<?php elseif ($checkoutStatus === 'canceled'): ?>
<div class="flash flash-info mb-6">
    Checkout was canceled. Your subscription was not changed.
</div>
<?php endif; ?>

<?php if ($billingBanner): ?>
<?php $bannerCls = $billingBanner['type'] === 'warning' ? 'flash-error' : 'flash-info'; ?>
<div class="flash <?= $bannerCls ?> mb-6"><?= View::e($billingBanner['message']) ?></div>
<?php endif; ?>

<!-- ── Current Plan ────────────────────────────────────────────────────────── -->
<h2 class="mb-3">Current Plan</h2>

<?php if ($activeSub): ?>

<?php if ($isPlanLegacy): ?>
<div class="card-warn mw-520 mb-4">
    You are on a legacy version of this plan. It is no longer publicly available. Your access continues
    as long as your subscription remains active.
</div>
<?php elseif ($isFreePlan): ?>
<p class="text-92 text-muted-3 mb-3 mw-520">
    You are on the Free plan. Use the plan comparison below to upgrade.
</p>
<?php else: ?>
<p class="text-92 text-muted-3 mb-3 mw-520">
    You are on the <strong><?= View::e($activeSub['plan_display_name']) ?></strong> plan.
    <?php if (!$stripeEnabled): ?>
    Plan changes are reviewed manually by our team. Online checkout is not yet available —
    no charges apply until billing is confirmed with you directly.
    <?php endif; ?>
</p>
<?php endif; ?>

<table class="mw-480 mb-8">
    <tr>
        <th class="col-160">Plan</th>
        <td>
            <strong><?= View::e($activeSub['plan_display_name']) ?></strong>
            <span class="text-faint text-sm">(<?= View::e($activeSub['plan_internal_name']) ?>)</span>
            <?php if ($isPlanLegacy): ?>
            <span class="badge badge-legacy ml-2">legacy</span>
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
    <?php if (!empty($activeSub['billing_status']) && $activeSub['billing_status'] !== 'not_applicable'): ?>
    <tr>
        <th>Billing Status</th>
        <td><?= View::e(str_replace('_', ' ', $activeSub['billing_status'])) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!empty($activeSub['current_period_end'])): ?>
    <?php
    $isScheduledCancel = (bool) ($activeSub['cancel_at_period_end'] ?? false);
    $isBillingCanceled = ($activeSub['billing_status'] ?? '') === 'canceled';
    $periodEndLabel    = ($isScheduledCancel || $isBillingCanceled) ? 'Access Until' : 'Renews On';
    ?>
    <tr>
        <th><?= $periodEndLabel ?></th>
        <td><?= View::e(date('F j, Y', strtotime($activeSub['current_period_end']))) ?></td>
    </tr>
    <?php endif; ?>
</table>

<?php if ($showCancelButton): ?>
<div class="mb-8">
    <form method="post" action="/account/subscription/cancel-stripe"
          data-confirm="Cancel your subscription? You will retain access until the end of the current billing period.">
        <?= CsrfService::field() ?>
        <button type="submit" class="btn btn-danger btn-sm">Cancel Subscription</button>
    </form>
    <p class="text-82 text-muted mt-2">
        You will keep your current plan features until the end of the billing period.
        Your subscription will not renew.
    </p>
</div>
<?php endif; ?>

<?php else: ?>
<p class="text-muted-2 mb-8">You do not have an active subscription.</p>
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
<h2 class="mb-3">Current Usage &amp; Limits</h2>
<table class="mw-380 mb-8 text-base">
    <?php if ($maxQr !== null): ?>
    <tr>
        <th class="col-200 fw-medium">Active QR usage</th>
        <td>
            <?= (int) $activeQrCount ?> / <?= View::e((string) $maxQr) ?>
            <?php if ((int) $activeQrCount >= (int) $maxQr): ?>
            <span class="text-danger text-82 ml-2">limit reached</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php if ($archivedQrCount > 0): ?>
    <tr>
        <th class="fw-medium">Archived QR codes</th>
        <td>
            <?= (int) $archivedQrCount ?>
            <span class="text-muted-2 text-82 ml-2">do not count toward limit</span>
        </td>
    </tr>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($analyticsRetain !== null): ?>
    <tr>
        <th class="fw-medium">Analytics Retention</th>
        <td><?= View::e((string) $analyticsRetain) ?> days</td>
    </tr>
    <?php endif; ?>
    <?php if ($canCustomSlug !== null): ?>
    <tr>
        <th class="fw-medium">Custom Short Links</th>
        <td><?= $canCustomSlug ? '<span class="text-success">&#10003; available</span>' : '<span class="text-faint">not available</span>' ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($canSvg !== null): ?>
    <tr>
        <th class="fw-medium">SVG Export</th>
        <td><?= $canSvg ? '<span class="text-success">&#10003; available</span>' : '<span class="text-faint">not available</span>' ?></td>
    </tr>
    <?php endif; ?>
</table>
<?php endif; ?>

<!-- ── Pending Requests ───────────────────────────────────────────────────── -->
<h2 class="mb-3">Pending Plan-Change Requests</h2>

<?php if (empty($pendingRequests)): ?>
<p class="text-muted-2 text-sm mb-8">You do not have any pending plan-change requests.</p>
<?php else: ?>
<p class="text-muted-3 text-88 mb-3 mw-520">
    The following requests are waiting for admin review. You will be notified once a decision is made.
    Canceling a request does not affect your current subscription.
</p>
<table class="mw-620 mb-8">
    <thead>
        <tr>
            <th>Requested Plan</th>
            <th class="col-140">Current Plan at Request</th>
            <th class="col-100">Submitted</th>
            <th class="col-90"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($pendingRequests as $req): ?>
    <tr>
        <td>
            <strong><?= View::e($req['requested_plan_name']) ?></strong>
            <span class="text-faint text-83">(<?= View::e($req['requested_plan_internal']) ?>)</span>
        </td>
        <td class="text-sm text-muted">
            <?= $req['current_plan_name'] ? View::e($req['current_plan_name']) : '<span class="text-faint">none</span>' ?>
        </td>
        <td class="text-sm text-muted"><?= View::e(substr($req['requested_at'], 0, 10)) ?></td>
        <td>
            <form method="post" action="/account/subscription/request-cancel"
                  data-confirm="Cancel this plan-change request? Your current subscription will not be affected.">
                <?= CsrfService::field() ?>
                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm">Cancel Request</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- ── Request History ────────────────────────────────────────────────────── -->
<?php if (!empty($requestHistory)): ?>
<h2 class="mb-3">Recent Request History</h2>
<table class="mw-680 mb-8 text-88">
    <thead>
        <tr>
            <th>Requested Plan</th>
            <th class="col-100">Status</th>
            <th class="col-100">Submitted</th>
            <th class="col-100">Reviewed</th>
            <th>Outcome</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($requestHistory as $h): ?>
    <tr>
        <td>
            <?= View::e($h['requested_plan_name']) ?>
            <span class="text-faint text-82">(<?= View::e($h['requested_plan_internal']) ?>)</span>
        </td>
        <td class="status-<?= View::e($h['status']) ?>">
            <?= View::e($h['status']) ?>
        </td>
        <td class="text-muted"><?= View::e(substr($h['requested_at'], 0, 10)) ?></td>
        <td class="text-muted">
            <?= $h['reviewed_at'] ? View::e(substr($h['reviewed_at'], 0, 10)) : '<span class="text-dim">—</span>' ?>
        </td>
        <td class="text-muted text-sm">
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
<h2 class="mb-3">Available Plans</h2>

<?php if (empty($plans)): ?>
<p class="text-muted-2 mb-8">No plans are currently available.</p>
<?php else: ?>

<div class="scroll-x mb-2">
<table class="min-w-520 mb-0">
    <thead>
        <tr>
            <th class="col-170 text-82 text-muted">Feature</th>
            <?php foreach ($plans as $p): ?>
            <th class="text-center">
                <?= View::e($p['display_name']) ?>
                <?php if ($currentPlanId === (int) $p['id']): ?>
                <div class="text-xs text-success fw-medium">&#10003; current</div>
                <?php endif; ?>
            </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($displayFeatures as $key => $label): ?>
    <tr>
        <td class="text-sm"><?= View::e($label) ?></td>
        <?php foreach ($plans as $p): ?>
        <td class="text-center text-88">
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
        // Determine checkout cycle when Stripe is enabled
        $planPrices = $stripePricesByPlan[$pid] ?? [];
        $hasMonthly = isset($planPrices['monthly']);
        $hasYearly  = isset($planPrices['yearly']);
        ?>
        <td class="text-center pt-3 pb-2">
            <?php if ($isCurrent): ?>
                <span class="btn-disabled btn-disabled-sm">Current Plan</span>
            <?php elseif ($isPending): ?>
                <span class="btn-disabled btn-disabled-sm">Request Pending</span>
            <?php elseif ($isFree): ?>
                <?php if ($currentSubscriptionIsStripeBacked): ?>
                <span class="btn-disabled btn-disabled-sm text-83">Cancel paid subscription above</span>
                <?php else: ?>
                <form method="post" action="/account/subscription/change">
                    <?= CsrfService::field() ?>
                    <input type="hidden" name="plan_id" value="<?= $pid ?>">
                    <button type="submit" class="btn btn-sm">Switch to Free</button>
                </form>
                <?php endif; ?>
            <?php elseif ($stripeEnabled): ?>
                <?php if ($hasMonthly || $hasYearly): ?>
                <?php if ($hasMonthly): ?>
                <form method="post" action="/account/subscription/checkout" class="mb-1">
                    <?= CsrfService::field() ?>
                    <input type="hidden" name="plan_id" value="<?= $pid ?>">
                    <input type="hidden" name="billing_cycle" value="monthly">
                    <button type="submit" class="btn btn-sm">Subscribe Monthly</button>
                </form>
                <?php endif; ?>
                <?php if ($hasYearly): ?>
                <form method="post" action="/account/subscription/checkout">
                    <?= CsrfService::field() ?>
                    <input type="hidden" name="plan_id" value="<?= $pid ?>">
                    <input type="hidden" name="billing_cycle" value="yearly">
                    <button type="submit" class="btn btn-sm">Subscribe Yearly</button>
                </form>
                <?php endif; ?>
                <?php else: ?>
                <span class="btn-disabled btn-disabled-sm text-83">Online checkout<br>not configured</span>
                <?php endif; ?>
            <?php else: ?>
                <form method="post" action="/account/subscription/change">
                    <?= CsrfService::field() ?>
                    <input type="hidden" name="plan_id" value="<?= $pid ?>">
                    <button type="submit" class="btn btn-sm">Request Review</button>
                </form>
            <?php endif; ?>
        </td>
        <?php endforeach; ?>
    </tr>
    </tbody>
</table>
</div>

<p class="text-82 text-muted mt-2">
    Switching to Free takes effect immediately.
    <?php if ($stripeEnabled): ?>
    Paid plan subscriptions are processed via Stripe Checkout. Your subscription activates
    after payment is confirmed by Stripe.
    <?php else: ?>
    Requests for other plans are reviewed manually by our team. No charges apply until
    billing is confirmed with you directly.
    <?php endif; ?>
</p>

<?php endif; ?>
