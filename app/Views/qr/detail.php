<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1rem">
    <h1><?= View::e($qr['name']) ?></h1>
    <a href="/qr" style="color:#666;font-size:0.9rem">&larr; My QR Codes</a>
</div>

<table style="max-width:640px;margin-bottom:2rem">
    <tr>
        <th style="width:160px">Short URL</th>
        <td>
            <a href="<?= View::e($shortUrl) ?>" target="_blank"><?= View::e($shortUrl) ?></a>
            <small style="color:#888;margin-left:0.5rem">(slug: <?= View::e($qr['slug']) ?>)</small>
        </td>
    </tr>
    <tr>
        <th>Destination</th>
        <td>
            <a href="<?= View::e($qr['current_target_url']) ?>" target="_blank">
                <?= View::e($qr['current_target_url']) ?>
            </a>
        </td>
    </tr>
    <tr>
        <th>Status</th>
        <td class="status-<?= View::e($qr['status']) ?>">
            <?= View::e(ucfirst($qr['status'])) ?>
        </td>
    </tr>
    <tr>
        <th>Created</th>
        <td><?= View::e($qr['created_at']) ?></td>
    </tr>
    <tr>
        <th>Last updated</th>
        <td><?= View::e($qr['sl_updated_at']) ?></td>
    </tr>
</table>

<h2 style="margin-bottom:0.75rem">Actions</h2>
<div class="actions-group">

    <?php if ($canEditDestination): ?>
    <a href="/qr/<?= (int) $qr['id'] ?>/edit" class="btn btn-secondary">Edit Destination</a>
    <?php else: ?>
    <span class="btn-disabled" title="Not available on your plan">Edit Destination</span>
    <?php endif; ?>

    <?php if ($canPauseLinks && $qr['status'] === 'active'): ?>
    <form method="post" action="/qr/<?= (int) $qr['id'] ?>/pause" style="display:inline">
        <button type="submit" class="btn btn-secondary">Pause</button>
    </form>
    <?php elseif ($canPauseLinks && $qr['status'] === 'paused'): ?>
    <form method="post" action="/qr/<?= (int) $qr['id'] ?>/resume" style="display:inline">
        <button type="submit" class="btn">Resume</button>
    </form>
    <?php elseif (!$canPauseLinks): ?>
    <span class="btn-disabled" title="Not available on your plan">Pause / Resume</span>
    <?php endif; ?>

</div>

<h2 style="margin-bottom:0.75rem">Downloads</h2>
<div class="actions-group">

    <?php if ($canExportPng): ?>
    <a href="/qr/<?= (int) $qr['id'] ?>/download/png" class="btn btn-secondary">Download PNG</a>
    <?php else: ?>
    <span class="btn-disabled" title="Not available on your plan">Download PNG</span>
    <?php endif; ?>

    <?php if ($canExportSvg): ?>
    <a href="/qr/<?= (int) $qr['id'] ?>/download/svg" class="btn btn-secondary">Download SVG</a>
    <?php else: ?>
    <span class="btn-disabled" title="Not available on your plan">Download SVG</span>
    <?php endif; ?>

</div>
<p style="font-size:0.8rem;color:#888;margin-top:0.5rem">
    QR code encodes: <code><?= View::e($shortUrl) ?></code>
</p>
