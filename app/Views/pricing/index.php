<?php
// Curated feature display list: key => friendly label
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
?>
<h1 class="mb-2">Pricing</h1>
<p class="mb-2">Simple, transparent plans for every use case.</p>
<?php if (!$stripeEnabled): ?>
<p class="text-sm text-muted mb-8">
    Paid plan requests are reviewed manually. Online billing is not yet enabled.
</p>
<?php else: ?>
<p class="text-sm text-muted mb-8">
    Paid plans are billed via Stripe Checkout.
</p>
<?php endif; ?>

<?php if (empty($plans)): ?>
<p class="text-muted-2">No plans are currently available. Check back soon.</p>
<?php else: ?>

<div class="scroll-x">
<table class="min-w-560 mb-4">
    <thead>
        <tr>
            <th class="col-180 text-85 text-muted">Feature</th>
            <?php foreach ($plans as $p): ?>
            <th class="text-center">
                <div class="text-105 fw-bold"><?= View::e($p['display_name']) ?></div>
                <div class="text-82 text-muted fw-normal">
                    <?php
                    $hasMonthly = $p['monthly_price_cents'] !== null && (int) $p['monthly_price_cents'] > 0;
                    $hasYearly  = $p['yearly_price_cents']  !== null && (int) $p['yearly_price_cents']  > 0;
                    if ($p['internal_name'] === 'free_v1') {
                        echo 'Free';
                    } elseif ($hasMonthly) {
                        echo '$' . number_format((int) $p['monthly_price_cents'] / 100, 2) . '/mo';
                    } elseif ($hasYearly) {
                        echo '$' . number_format((int) $p['yearly_price_cents'] / 100, 2) . '/yr';
                    } else {
                        echo 'Contact us';
                    }
                    ?>
                </div>
                <?php if ($currentPlanId === (int) $p['id']): ?>
                <div class="mt-1">
                    <span class="badge badge-current">Current</span>
                </div>
                <?php elseif (in_array((int) $p['id'], $pendingPlanIds, true)): ?>
                <div class="mt-1">
                    <span class="badge badge-pending">Requested</span>
                </div>
                <?php endif; ?>
            </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($displayFeatures as $key => $label): ?>
        <tr>
            <td class="text-88"><?= View::e($label) ?></td>
            <?php foreach ($plans as $p): ?>
            <td class="text-center text-base">
                <?= $fv($features[(int) $p['id']] ?? [], $key) ?>
            </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    <tr>
        <td></td>
        <?php foreach ($plans as $p): ?>
        <td class="text-center pt-4 pb-2">
            <?php
            $pid       = (int) $p['id'];
            $isCurrent = $currentPlanId === $pid;
            $isPending = in_array($pid, $pendingPlanIds, true);
            $isFree    = $p['internal_name'] === 'free_v1';
            $planPrices = $stripePricesByPlan[$pid] ?? [];
            $hasMonthly = isset($planPrices['monthly']);
            $hasYearly  = isset($planPrices['yearly']);
            ?>
            <?php if (!$currentUser): ?>
                <a href="/register" class="btn btn-sm">Create Account</a>
            <?php elseif ($isCurrent): ?>
                <span class="btn-disabled btn-disabled-sm">Current Plan</span>
            <?php elseif ($isPending): ?>
                <span class="btn-disabled btn-disabled-sm">Request Pending</span>
            <?php elseif ($isFree): ?>
                <?php if ($currentSubscriptionIsStripeBacked): ?>
                <span class="btn-disabled btn-disabled-sm text-83">Cancel paid subscription<br>from Account</span>
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
                <span class="btn-disabled btn-disabled-sm">Not available</span>
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
    Paid plans use Stripe Checkout. Your subscription activates after payment is confirmed.
    <?php else: ?>
    All other plan requests are reviewed manually. No charges apply — billing is not yet automated.
    <?php endif; ?>
</p>

<?php endif; ?>
