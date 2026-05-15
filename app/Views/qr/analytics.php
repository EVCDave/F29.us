<div class="page-header">
    <h1><?= View::e($qr['name']) ?> — Analytics</h1>
    <div class="d-flex gap-3 align-center">
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
        <a href="/qr/<?= (int) $qr['id'] ?>" class="back-link">&larr; Back</a>
    </div>
</div>

<!-- ── Date range + bot filter form ──────────────────────────────────────── -->
<form method="get" action="/qr/<?= (int) $qr['id'] ?>/analytics" class="filter-form mb-5">
    <div>
        <label for="from" class="filter-label">From</label>
        <input type="date" id="from" name="from"
               value="<?= View::e($fromDate) ?>"
               min="<?= View::e($allowedFrom) ?>"
               max="<?= View::e($toDate) ?>">
    </div>
    <div>
        <label for="to" class="filter-label">To</label>
        <input type="date" id="to" name="to"
               value="<?= View::e($toDate) ?>"
               min="<?= View::e($allowedFrom) ?>"
               max="<?= date('Y-m-d') ?>">
    </div>
    <div>
        <label class="d-flex align-center gap-2 text-88 text-muted-3">
            <input type="checkbox" name="include_bots" value="1"<?= $includeBots ? ' checked' : '' ?>>
            Include bot traffic
        </label>
    </div>
    <button type="submit" class="btn btn-secondary">Apply</button>
    <a href="/qr/<?= (int) $qr['id'] ?>/analytics" class="filter-link">Reset</a>
</form>

<!-- ── Retention / clamp notice ──────────────────────────────────────────── -->
<div class="filter-panel mb-6">
    <p class="mb-0 text-83 text-muted-3">
        Your plan shows up to <?= (int) $retentionDays ?> days of analytics (since <?= View::e($allowedFrom) ?>).
        Older scan events are retained by the system but are not visible on your current plan.
        <?php if ($clamped): ?>
        <strong class="text-warning"> The requested start date was outside your retention window and was adjusted to <?= View::e($allowedFrom) ?>.</strong>
        <?php endif; ?>
    </p>
</div>

<!-- ── Summary cards ─────────────────────────────────────────────────────── -->
<div class="stat-grid mb-8">

    <div class="stat-card">
        <div class="stat-value"><?= (int) $humanScans ?></div>
        <div class="stat-label">Human scans</div>
    </div>

    <div class="stat-card <?= $botScans > 0 ? 'admin-stat-card--warn' : '' ?>">
        <div class="stat-value <?= $botScans > 0 ? 'stat-value-paused' : '' ?>"><?= (int) $botScans ?></div>
        <div class="stat-label <?= $botScans > 0 ? 'text-warning' : '' ?>">
            Bot scans <?= $includeBots ? '<span class="fw-normal">(included)</span>' : '<span class="fw-normal">(excluded)</span>' ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-value"><?= number_format((float) $avgPerDay, 1) ?></div>
        <div class="stat-label">Avg scans / day</div>
    </div>

    <?php if ($peakDay): ?>
    <div class="stat-card">
        <div class="stat-value"><?= (int) $peakDay['total'] ?></div>
        <div class="stat-label">Peak day (<?= View::e($peakDay['scan_date']) ?>)</div>
    </div>
    <?php endif; ?>

    <div class="stat-card">
        <div class="stat-value text-95 mt-3"><?= View::e($fromDate) ?> – <?= View::e($toDate) ?></div>
        <div class="stat-label">Selected range</div>
    </div>

</div>

<!-- ── Scans by day ───────────────────────────────────────────────────────── -->
<h2 class="mb-2">Scans by Day</h2>
<?php if ($includeBots): ?>
<p class="text-82 text-warning mb-3">Bot traffic is included in daily counts.</p>
<?php else: ?>
<p class="text-82 text-muted-2 mb-3">Bot traffic excluded.</p>
<?php endif; ?>

<?php
$nonZeroDays = array_filter($dailyCounts, fn($r) => (int) $r['total'] > 0);
$maxDay      = !empty($nonZeroDays) ? (int) max(array_column($dailyCounts, 'total')) : 0;
?>

<?php if ($maxDay === 0): ?>
<p class="text-muted-2">No scans recorded in this period.</p>
<?php else: ?>
<table class="mw-580 mb-6">
    <thead>
        <tr>
            <th class="col-110">Date</th>
            <th class="text-right col-60">Scans</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($dailyCounts as $row): ?>
        <?php if ((int) $row['total'] === 0) continue; ?>
        <tr>
            <td class="text-muted text-88"><?= View::e($row['scan_date']) ?></td>
            <td class="text-right text-88"><?= (int) $row['total'] ?></td>
            <td>
                <?php $pct = $maxDay > 0 ? (int) round((int) $row['total'] / $maxDay * 100) : 0; ?>
                <div class="bar" data-bar-pct="<?= max(2, $pct) ?>"></div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
$zeroCount = count($dailyCounts) - count($nonZeroDays);
if ($zeroCount > 0):
?>
<p class="text-2xs text-dim mt-neg-2 mb-6">
    <?= $zeroCount ?> day<?= $zeroCount === 1 ? '' : 's' ?> with zero scans not shown.
</p>
<?php endif; ?>
<?php endif; ?>

<!-- ── Devices ───────────────────────────────────────────────────────────── -->
<?php if (!empty($deviceBreakdown)): ?>
<h2 class="mb-3 mt-7">Devices</h2>
<?php $deviceTotal = array_sum(array_column($deviceBreakdown, 'total')); ?>
<table class="mw-360">
    <thead>
        <tr>
            <th>Device</th>
            <th class="text-right col-60">Scans</th>
            <th class="text-right col-70">Share</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($deviceBreakdown as $row): ?>
        <tr>
            <td><?= View::e(ucfirst($row['device_type'] ?? 'Unknown')) ?></td>
            <td class="text-right"><?= (int) $row['total'] ?></td>
            <td class="text-right text-muted-2 text-sm">
                <?= $deviceTotal > 0 ? number_format((int) $row['total'] / $deviceTotal * 100, 1) : '0.0' ?>%
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p class="text-2xs text-dim mt-1">
    <?= $includeBots ? 'Bot traffic included in device counts.' : 'Bot traffic excluded from device counts.' ?>
</p>
<?php endif; ?>

<!-- ── Top referers ──────────────────────────────────────────────────────── -->
<?php if (!empty($topReferers)): ?>
<h2 class="mb-3 mt-7">Top Referers</h2>
<?php $refTotal = array_sum(array_column($topReferers, 'total')); ?>
<table class="mw-680">
    <thead>
        <tr>
            <th>Referer</th>
            <th class="text-right col-60">Scans</th>
            <th class="text-right col-70">Share</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($topReferers as $row): ?>
        <?php $isDirect = $row['referer'] === 'Direct / Unknown'; ?>
        <tr>
            <td class="text-ellipsis mw-520">
                <?php if ($isDirect): ?>
                <span class="text-faint italic">Direct / Unknown</span>
                <?php else: ?>
                <?= View::e($row['referer']) ?>
                <?php endif; ?>
            </td>
            <td class="text-right"><?= (int) $row['total'] ?></td>
            <td class="text-right text-muted-2 text-sm">
                <?= $refTotal > 0 ? number_format((int) $row['total'] / $refTotal * 100, 1) : '0.0' ?>%
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p class="text-2xs text-dim mt-1">
    Top 10. <?= $includeBots ? 'Bot traffic included.' : 'Bot traffic excluded.' ?>
</p>
<?php elseif ($humanScans > 0): ?>
<h2 class="mb-2 mt-7">Top Referers</h2>
<p class="text-muted-2 text-base">No referer data available for this period.</p>
<?php endif; ?>

<?php if (empty($deviceBreakdown) && $humanScans === 0): ?>
<p class="text-muted-2 mt-6">No scan activity to show for this date range.</p>
<?php endif; ?>
