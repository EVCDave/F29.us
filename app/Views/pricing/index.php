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
    'can_upload_qr_logo'       => 'QR Logo Upload',
    'qr_logo_max_size_kb'      => 'Logo Max Size',
    'qr_logo_max_percent'      => 'Logo Coverage',
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
    return View::e($f['feature_value']);
};
?>
<h1 class="mb-2">Pricing</h1>
<p class="mb-2">Simple, transparent plans for every use case.</p>
<p class="text-sm text-muted mb-8">
    Paid plan requests are reviewed manually. Online billing is not yet enabled.
</p>

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
            ?>
            <?php if (!$currentUser): ?>
                <a href="/register" class="btn btn-sm">Create Account</a>
            <?php elseif ($isCurrent): ?>
                <span class="btn-disabled btn-disabled-sm">Current Plan</span>
            <?php elseif ($isPending): ?>
                <span class="btn-disabled btn-disabled-sm">Request Pending</span>
            <?php else: ?>
                <form method="post" action="/account/subscription/change">
                    <?= CsrfService::field() ?>
                    <input type="hidden" name="plan_id" value="<?= $pid ?>">
                    <button type="submit" class="btn btn-sm">
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

<p class="text-82 text-muted mt-2">
    Switching to Free takes effect immediately. All other plan requests are reviewed manually.
    No charges apply — billing is not yet automated.
</p>

<?php endif; ?>
