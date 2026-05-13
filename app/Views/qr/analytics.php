<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1rem">
    <h1><?= View::e($qr['name']) ?> — Analytics</h1>
    <a href="/qr/<?= (int) $qr['id'] ?>" style="color:#666;font-size:0.9rem">&larr; Back to QR Code</a>
</div>

<p style="color:#666;font-size:0.88rem;margin-bottom:1.75rem">
    Showing the last <?= (int) $retentionDays ?> days for
    <strong><?= View::e($shortUrl) ?></strong>.
    Bot traffic is excluded from totals.
    <?php if ($botCount > 0): ?>
    <span style="color:#92400e"><?= (int) $botCount ?> bot scan<?= $botCount === 1 ? '' : 's' ?> recorded separately.</span>
    <?php endif; ?>
</p>

<!-- Summary stat cards -->
<div style="display:flex;gap:1.25rem;margin-bottom:2rem;flex-wrap:wrap">
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1rem 1.5rem;min-width:130px">
        <div style="font-size:2rem;font-weight:700;color:#1a1a2e"><?= (int) $totalScans ?></div>
        <div style="font-size:0.8rem;color:#666;margin-top:0.15rem">Total scans</div>
    </div>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1rem 1.5rem;min-width:130px">
        <div style="font-size:2rem;font-weight:700;color:#1a1a2e"><?= count($dailyCounts) ?></div>
        <div style="font-size:0.8rem;color:#666;margin-top:0.15rem">Active days</div>
    </div>
    <?php if ($botCount > 0): ?>
    <div style="background:#fff;border:1px solid #fde68a;border-radius:6px;padding:1rem 1.5rem;min-width:130px">
        <div style="font-size:2rem;font-weight:700;color:#92400e"><?= (int) $botCount ?></div>
        <div style="font-size:0.8rem;color:#92400e;margin-top:0.15rem">Bot scans</div>
    </div>
    <?php endif; ?>
</div>

<!-- Scans by day -->
<h2 style="margin-bottom:0.75rem">Scans by Day</h2>
<?php if (empty($dailyCounts)): ?>
<p style="color:#666">No human scans recorded in this period.</p>
<?php else: ?>
<?php $maxDay = (int) max(array_column($dailyCounts, 'total')); ?>
<table style="max-width:540px">
    <thead>
        <tr>
            <th>Date</th>
            <th style="text-align:right;width:70px">Scans</th>
            <th style="width:180px"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($dailyCounts as $row): ?>
        <tr>
            <td><?= View::e($row['scan_date']) ?></td>
            <td style="text-align:right"><?= (int) $row['total'] ?></td>
            <td>
                <?php $pct = $maxDay > 0 ? (int) round((int) $row['total'] / $maxDay * 100) : 0; ?>
                <div style="background:#1a1a2e;height:6px;border-radius:3px;width:<?= $pct ?>%"></div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- Device breakdown -->
<?php if (!empty($deviceBreakdown)): ?>
<h2 style="margin-bottom:0.75rem;margin-top:1.75rem">Devices</h2>
<table style="max-width:300px">
    <thead>
        <tr>
            <th>Device</th>
            <th style="text-align:right">Scans</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($deviceBreakdown as $row): ?>
        <tr>
            <td><?= View::e(ucfirst($row['device_type'] ?? 'unknown')) ?></td>
            <td style="text-align:right"><?= (int) $row['total'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- Top referers -->
<?php if (!empty($topReferers)): ?>
<h2 style="margin-bottom:0.75rem;margin-top:1.75rem">Top Referers</h2>
<table style="max-width:680px">
    <thead>
        <tr>
            <th>Referer</th>
            <th style="text-align:right;width:70px">Scans</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($topReferers as $row): ?>
        <tr>
            <td style="max-width:580px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= View::e($row['referer']) ?>
            </td>
            <td style="text-align:right"><?= (int) $row['total'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<p style="font-size:0.8rem;color:#aaa;margin-top:2rem">
    Analytics retention: <?= (int) $retentionDays ?> days. Older data is not displayed on your current plan.
</p>
