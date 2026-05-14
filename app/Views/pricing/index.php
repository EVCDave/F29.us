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
<h1 style="margin-bottom:0.4rem">Pricing</h1>
<p style="margin-bottom:0.5rem">Simple, transparent plans for every use case.</p>
<p style="font-size:0.85rem;color:#6b7280;margin-bottom:2rem">
    Paid plan requests are reviewed manually. Online billing is not yet enabled.
</p>

<?php if (empty($plans)): ?>
<p style="color:#888">No plans are currently available. Check back soon.</p>
<?php else: ?>

<div style="overflow-x:auto">
<table style="min-width:560px;margin-bottom:1rem">
    <thead>
        <tr>
            <th style="width:180px;font-size:0.85rem;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em">Feature</th>
            <?php foreach ($plans as $p): ?>
            <th style="text-align:center;min-width:130px">
                <div style="font-size:1.05rem;font-weight:700"><?= View::e($p['display_name']) ?></div>
                <div style="font-size:0.8rem;color:#6b7280;margin-top:0.1rem;font-weight:400">
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
                <div style="margin-top:0.3rem">
                    <span style="font-size:0.72rem;background:#1a1a2e;color:#fff;padding:0.15rem 0.5rem;border-radius:3px">Current</span>
                </div>
                <?php elseif (in_array((int) $p['id'], $pendingPlanIds, true)): ?>
                <div style="margin-top:0.3rem">
                    <span style="font-size:0.72rem;background:#fef3c7;color:#92400e;border:1px solid #f59e0b;padding:0.15rem 0.5rem;border-radius:3px">Requested</span>
                </div>
                <?php endif; ?>
            </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($displayFeatures as $key => $label): ?>
        <tr>
            <td style="font-size:0.88rem;color:#374151"><?= View::e($label) ?></td>
            <?php foreach ($plans as $p): ?>
            <td style="text-align:center;font-size:0.9rem">
                <?= $fv($features[(int) $p['id']] ?? [], $key) ?>
            </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    <tr>
        <td></td>
        <?php foreach ($plans as $p): ?>
        <td style="text-align:center;padding-top:1rem;padding-bottom:0.5rem">
            <?php
            $pid       = (int) $p['id'];
            $isCurrent = $currentPlanId === $pid;
            $isPending = in_array($pid, $pendingPlanIds, true);
            $isFree    = $p['internal_name'] === 'free_v1';
            ?>
            <?php if (!$currentUser): ?>
                <a href="/register" class="btn" style="font-size:0.82rem;padding:0.35rem 1rem">Create Account</a>
            <?php elseif ($isCurrent): ?>
                <span class="btn-disabled" style="font-size:0.82rem;padding:0.35rem 1rem">Current Plan</span>
            <?php elseif ($isPending): ?>
                <span class="btn-disabled" style="font-size:0.82rem;padding:0.35rem 1rem">Request Pending</span>
            <?php else: ?>
                <form method="post" action="/account/subscription/change">
                    <?= CsrfService::field() ?>
                    <input type="hidden" name="plan_id" value="<?= $pid ?>">
                    <button type="submit" class="btn" style="font-size:0.82rem;padding:0.35rem 1rem">
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
    Switching to Free takes effect immediately. All other plan requests are reviewed manually.
    No charges apply — billing is not yet automated.
</p>

<?php endif; ?>
