<div style="display:flex;align-items:baseline;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem">
    <h1><?= View::e($qr['name']) ?> — Analytics</h1>
    <div style="display:flex;gap:0.6rem;align-items:center">
        <?php if ($canExportAnalytics): ?>
        <?php
        $exportUrl = '/qr/' . (int) $qr['id'] . '/analytics/export'
            . '?from=' . urlencode($fromDate)
            . '&to='   . urlencode($toDate)
            . ($includeBots ? '&include_bots=1' : '');
        ?>
        <a href="<?= View::e($exportUrl) ?>" class="btn btn-secondary">Export CSV</a>
        <?php else: ?>
        <span class="btn-disabled" title="Not available on your plan">Export CSV</span>
        <?php endif; ?>
        <a href="/qr/<?= (int) $qr['id'] ?>" style="color:#666;font-size:0.9rem">&larr; Back</a>
    </div>
</div>

<!-- ── Date range + bot filter form ──────────────────────────────────────── -->
<form method="get" action="/qr/<?= (int) $qr['id'] ?>/analytics"
      style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1.25rem">
    <div>
        <label for="from" style="display:block;font-size:0.82rem;font-weight:500;color:#555;margin-bottom:0.25rem">From</label>
        <input type="date" id="from" name="from"
               value="<?= View::e($fromDate) ?>"
               min="<?= View::e($allowedFrom) ?>"
               max="<?= View::e($toDate) ?>"
               style="padding:0.4rem 0.6rem;border:1px solid #ccc;border-radius:4px;font-size:0.9rem">
    </div>
    <div>
        <label for="to" style="display:block;font-size:0.82rem;font-weight:500;color:#555;margin-bottom:0.25rem">To</label>
        <input type="date" id="to" name="to"
               value="<?= View::e($toDate) ?>"
               min="<?= View::e($allowedFrom) ?>"
               max="<?= date('Y-m-d') ?>"
               style="padding:0.4rem 0.6rem;border:1px solid #ccc;border-radius:4px;font-size:0.9rem">
    </div>
    <div style="padding-bottom:0.1rem">
        <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.88rem;color:#444;cursor:pointer">
            <input type="checkbox" name="include_bots" value="1"<?= $includeBots ? ' checked' : '' ?>>
            Include bot traffic
        </label>
    </div>
    <button type="submit" class="btn btn-secondary">Apply</button>
    <a href="/qr/<?= (int) $qr['id'] ?>/analytics" style="font-size:0.85rem;color:#666;align-self:center">Reset</a>
</form>

<!-- ── Retention / clamp notice ──────────────────────────────────────────── -->
<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:5px;padding:0.6rem 1rem;margin-bottom:1.5rem;font-size:0.83rem;color:#555">
    Your plan shows up to <?= (int) $retentionDays ?> days of analytics (since <?= View::e($allowedFrom) ?>).
    Older scan events are retained by the system but are not visible on your current plan.
    <?php if ($clamped): ?>
    <strong style="color:#92400e"> The requested start date was outside your retention window and was adjusted to <?= View::e($allowedFrom) ?>.</strong>
    <?php endif; ?>
</div>

<!-- ── Summary cards ─────────────────────────────────────────────────────── -->
<div style="display:flex;gap:1rem;margin-bottom:2rem;flex-wrap:wrap">

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1rem 1.4rem;min-width:120px">
        <div style="font-size:1.9rem;font-weight:700;color:#1a1a2e"><?= (int) $humanScans ?></div>
        <div style="font-size:0.78rem;color:#666;margin-top:0.15rem">Human scans</div>
    </div>

    <div style="background:#fff;border:1px solid <?= $botScans > 0 ? '#fde68a' : '#e5e7eb' ?>;border-radius:6px;padding:1rem 1.4rem;min-width:120px">
        <div style="font-size:1.9rem;font-weight:700;color:<?= $botScans > 0 ? '#92400e' : '#1a1a2e' ?>"><?= (int) $botScans ?></div>
        <div style="font-size:0.78rem;color:<?= $botScans > 0 ? '#92400e' : '#666' ?>;margin-top:0.15rem">
            Bot scans <?= $includeBots ? '<span style="font-weight:400">(included)</span>' : '<span style="font-weight:400">(excluded)</span>' ?>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1rem 1.4rem;min-width:120px">
        <div style="font-size:1.9rem;font-weight:700;color:#1a1a2e"><?= number_format((float) $avgPerDay, 1) ?></div>
        <div style="font-size:0.78rem;color:#666;margin-top:0.15rem">Avg scans / day</div>
    </div>

    <?php if ($peakDay): ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1rem 1.4rem;min-width:120px">
        <div style="font-size:1.9rem;font-weight:700;color:#1a1a2e"><?= (int) $peakDay['total'] ?></div>
        <div style="font-size:0.78rem;color:#666;margin-top:0.15rem">Peak day (<?= View::e($peakDay['scan_date']) ?>)</div>
    </div>
    <?php endif; ?>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1rem 1.4rem;min-width:120px">
        <div style="font-size:0.95rem;font-weight:700;color:#1a1a2e;margin-top:0.3rem">
            <?= View::e($fromDate) ?> – <?= View::e($toDate) ?>
        </div>
        <div style="font-size:0.78rem;color:#666;margin-top:0.15rem">Selected range</div>
    </div>

</div>

<!-- ── Scans by day ───────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.5rem">Scans by Day</h2>
<?php if ($includeBots): ?>
<p style="font-size:0.82rem;color:#92400e;margin-bottom:0.75rem">Bot traffic is included in daily counts.</p>
<?php else: ?>
<p style="font-size:0.82rem;color:#888;margin-bottom:0.75rem">Bot traffic excluded.</p>
<?php endif; ?>

<?php
$nonZeroDays = array_filter($dailyCounts, fn($r) => (int) $r['total'] > 0);
$maxDay      = !empty($nonZeroDays) ? (int) max(array_column($dailyCounts, 'total')) : 0;
?>

<?php if ($maxDay === 0): ?>
<p style="color:#888">No scans recorded in this period.</p>
<?php else: ?>
<table style="max-width:580px;margin-bottom:1.5rem">
    <thead>
        <tr>
            <th style="width:110px">Date</th>
            <th style="text-align:right;width:60px">Scans</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($dailyCounts as $row): ?>
        <?php if ((int) $row['total'] === 0) continue; ?>
        <tr>
            <td style="color:#6b7280;font-size:0.88rem"><?= View::e($row['scan_date']) ?></td>
            <td style="text-align:right;font-size:0.88rem"><?= (int) $row['total'] ?></td>
            <td style="padding-left:0.5rem">
                <?php $pct = $maxDay > 0 ? (int) round((int) $row['total'] / $maxDay * 100) : 0; ?>
                <div style="background:#1a1a2e;height:8px;border-radius:4px;width:<?= max(2, $pct) ?>%;min-width:2px"></div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
$zeroCount = count($dailyCounts) - count($nonZeroDays);
if ($zeroCount > 0):
?>
<p style="font-size:0.8rem;color:#aaa;margin-top:-0.75rem;margin-bottom:1.5rem">
    <?= $zeroCount ?> day<?= $zeroCount === 1 ? '' : 's' ?> with zero scans not shown.
</p>
<?php endif; ?>
<?php endif; ?>

<!-- ── Devices ───────────────────────────────────────────────────────────── -->
<?php if (!empty($deviceBreakdown)): ?>
<h2 style="margin-bottom:0.75rem;margin-top:1.75rem">Devices</h2>
<?php $deviceTotal = array_sum(array_column($deviceBreakdown, 'total')); ?>
<table style="max-width:360px">
    <thead>
        <tr>
            <th>Device</th>
            <th style="text-align:right;width:60px">Scans</th>
            <th style="text-align:right;width:70px">Share</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($deviceBreakdown as $row): ?>
        <tr>
            <td><?= View::e(ucfirst($row['device_type'] ?? 'Unknown')) ?></td>
            <td style="text-align:right"><?= (int) $row['total'] ?></td>
            <td style="text-align:right;color:#888;font-size:0.85rem">
                <?= $deviceTotal > 0 ? number_format((int) $row['total'] / $deviceTotal * 100, 1) : '0.0' ?>%
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p style="font-size:0.8rem;color:#aaa;margin-top:0.25rem">
    <?= $includeBots ? 'Bot traffic included in device counts.' : 'Bot traffic excluded from device counts.' ?>
</p>
<?php endif; ?>

<!-- ── Top referers ──────────────────────────────────────────────────────── -->
<?php if (!empty($topReferers)): ?>
<h2 style="margin-bottom:0.75rem;margin-top:1.75rem">Top Referers</h2>
<?php $refTotal = array_sum(array_column($topReferers, 'total')); ?>
<table style="max-width:680px">
    <thead>
        <tr>
            <th>Referer</th>
            <th style="text-align:right;width:60px">Scans</th>
            <th style="text-align:right;width:70px">Share</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($topReferers as $row): ?>
        <?php $isDirect = $row['referer'] === 'Direct / Unknown'; ?>
        <tr>
            <td style="max-width:520px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?php if ($isDirect): ?>
                <span style="color:#9ca3af;font-style:italic">Direct / Unknown</span>
                <?php else: ?>
                <?= View::e($row['referer']) ?>
                <?php endif; ?>
            </td>
            <td style="text-align:right"><?= (int) $row['total'] ?></td>
            <td style="text-align:right;color:#888;font-size:0.85rem">
                <?= $refTotal > 0 ? number_format((int) $row['total'] / $refTotal * 100, 1) : '0.0' ?>%
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p style="font-size:0.8rem;color:#aaa;margin-top:0.25rem">
    Top 10. <?= $includeBots ? 'Bot traffic included.' : 'Bot traffic excluded.' ?>
</p>
<?php elseif ($humanScans > 0): ?>
<h2 style="margin-bottom:0.5rem;margin-top:1.75rem">Top Referers</h2>
<p style="color:#888;font-size:0.9rem">No referer data available for this period.</p>
<?php endif; ?>

<?php if (empty($deviceBreakdown) && $humanScans === 0): ?>
<p style="color:#888;margin-top:1.5rem">No scan activity to show for this date range.</p>
<?php endif; ?>
