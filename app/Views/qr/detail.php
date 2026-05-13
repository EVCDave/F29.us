<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1rem">
    <h1><?= View::e($qr['name']) ?></h1>
    <a href="/qr" style="color:#666;font-size:0.9rem">&larr; My QR Codes</a>
</div>

<?php if ($flash): ?>
<div class="notice" style="display:block;margin-bottom:1.25rem;
    <?= $flash['type'] === 'error'   ? 'background:#fef2f2;border-color:#fca5a5;color:#991b1b;' : '' ?>
    <?= $flash['type'] === 'success' ? 'background:#f0fdf4;border-color:#86efac;color:#166534;' : '' ?>
    <?= $flash['type'] === 'info'    ? 'background:#eff6ff;border-color:#93c5fd;color:#1e40af;' : '' ?>">
    <?= View::e($flash['text']) ?>
</div>
<?php endif; ?>

<div style="display:flex;gap:2rem;align-items:flex-start;flex-wrap:wrap;margin-bottom:2rem">

    <!-- QR preview -->
    <?php if ($qrPreviewSvg): ?>
    <div style="flex-shrink:0">
        <img
            src="data:image/svg+xml;base64,<?= $qrPreviewSvg ?>"
            alt="QR code for <?= View::e($qr['name']) ?>"
            style="width:160px;height:160px;border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;display:block"
        >
    </div>
    <?php endif; ?>

    <!-- Details table -->
    <div style="flex:1;min-width:280px">
        <table style="margin-bottom:0">
            <tr>
                <th style="width:140px">Short URL</th>
                <td>
                    <span id="short-url-text"><?= View::e($shortUrl) ?></span>
                    <button
                        type="button"
                        onclick="navigator.clipboard.writeText(document.getElementById('short-url-text').textContent).then(function(){var b=document.getElementById('copy-btn');b.textContent='Copied!';setTimeout(function(){b.textContent='Copy';},1800);})"
                        id="copy-btn"
                        style="margin-left:0.5rem;font-size:0.78rem;padding:0.15rem 0.5rem;border:1px solid #c0c0cc;border-radius:3px;background:#f9fafb;color:#374151;cursor:pointer"
                    >Copy</button>
                    <a href="<?= View::e($shortUrl) ?>" target="_blank" style="margin-left:0.5rem;font-size:0.82rem;color:#0066cc">&nearr;</a>
                    <small style="display:block;color:#888;margin-top:0.15rem">slug: <?= View::e($qr['slug']) ?></small>
                </td>
            </tr>
            <tr>
                <th>Destination</th>
                <td>
                    <a href="<?= View::e($qr['current_target_url']) ?>" target="_blank"
                       style="word-break:break-all">
                        <?= View::e($qr['current_target_url']) ?>
                    </a>
                </td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <span class="status-<?= View::e($qr['status']) ?>"><?= View::e(ucfirst($qr['status'])) ?></span>
                    <?php if ($qr['status'] === 'paused'): ?>
                    <span style="font-size:0.8rem;color:#888;margin-left:0.5rem">— scans show an unavailable page</span>
                    <?php elseif ($qr['status'] === 'archived'): ?>
                    <span style="font-size:0.8rem;color:#888;margin-left:0.5rem">— scans show an unavailable page</span>
                    <?php elseif ($qr['status'] === 'disabled'): ?>
                    <span style="font-size:0.8rem;color:#888;margin-left:0.5rem">— contact support</span>
                    <?php endif; ?>
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
    </div>

</div>

<h2 style="margin-bottom:0.75rem">Actions</h2>
<div class="actions-group">

    <a href="/qr/<?= (int) $qr['id'] ?>/analytics" class="btn btn-secondary">Analytics</a>

    <?php if ($qr['status'] !== 'archived' && $qr['status'] !== 'disabled'): ?>
    <a href="/qr/<?= (int) $qr['id'] ?>/edit" class="btn btn-secondary">Edit</a>
    <?php endif; ?>

    <?php if ($canPauseLinks && $qr['status'] === 'active'): ?>
    <form method="post" action="/qr/<?= (int) $qr['id'] ?>/pause" style="display:inline">
        <?= CsrfService::field() ?>
        <button type="submit" class="btn btn-secondary">Pause</button>
    </form>
    <?php elseif ($canPauseLinks && $qr['status'] === 'paused'): ?>
    <form method="post" action="/qr/<?= (int) $qr['id'] ?>/resume" style="display:inline">
        <?= CsrfService::field() ?>
        <button type="submit" class="btn">Resume</button>
    </form>
    <?php elseif (!$canPauseLinks && ($qr['status'] === 'active' || $qr['status'] === 'paused')): ?>
    <span class="btn-disabled" title="Not available on your plan">Pause / Resume</span>
    <?php endif; ?>

    <?php if (in_array($qr['status'], ['active', 'paused'], true)): ?>
    <form method="post" action="/qr/<?= (int) $qr['id'] ?>/archive" style="display:inline"
          onsubmit="return confirm('Archive this QR code? It will stop redirecting until restored.')">
        <?= CsrfService::field() ?>
        <button type="submit" class="btn btn-secondary" style="color:#6b7280">Archive</button>
    </form>
    <?php elseif ($qr['status'] === 'archived'): ?>
    <form method="post" action="/qr/<?= (int) $qr['id'] ?>/restore" style="display:inline">
        <?= CsrfService::field() ?>
        <button type="submit" class="btn">Restore</button>
    </form>
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
