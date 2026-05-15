<div class="page-header page-header-lg">
    <h1><?= View::e($qr['name']) ?></h1>
    <a href="/qr" class="back-link">&larr; My QR Codes</a>
</div>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<?php if ($qr['status'] === 'disabled'): ?>
<div class="flash flash-error">
    This QR code has been disabled by an administrator and will not redirect. Contact support if you believe this is an error.
</div>
<?php endif; ?>

<?php if ($qr['status'] === 'archived'): ?>
<div class="card-note">
    <strong>Archived</strong> — this QR code is not redirecting and does not count against your active QR code limit.
    <?php if ($atQuotaLimit): ?>
    <strong>Restore is unavailable</strong> — you have reached your active QR code limit.
    Archive another QR code to free up capacity.
    <?php else: ?>
    You can restore it to make it active again.
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="qr-layout">

    <?php if ($qrPreviewSvg): ?>
    <div class="qr-preview">
        <img
            src="data:image/svg+xml;base64,<?= $qrPreviewSvg ?>"
            alt="QR code for <?= View::e($qr['name']) ?>"
            class="qr-img"
        >
    </div>
    <?php endif; ?>

    <div class="qr-info">
        <table class="mb-0">
            <tr>
                <th class="col-140">Short URL</th>
                <td>
                    <span id="short-url-text"><?= View::e($shortUrl) ?></span>
                    <button
                        type="button"
                        data-copy-target="short-url-text"
                        id="copy-btn"
                        class="copy-btn"
                    >Copy</button>
                    <a href="<?= View::e($shortUrl) ?>" target="_blank" class="ml-2 text-82">&nearr;</a>
                    <small class="d-block text-muted-2 mt-1">slug: <?= View::e($qr['slug']) ?></small>
                </td>
            </tr>
            <tr>
                <th>Destination</th>
                <td>
                    <a href="<?= View::e($qr['current_target_url']) ?>" target="_blank" class="word-break">
                        <?= View::e($qr['current_target_url']) ?>
                    </a>
                </td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <span class="status-<?= View::e($qr['status']) ?>"><?= View::e(ucfirst($qr['status'])) ?></span>
                    <?php if (in_array($qr['status'], ['paused', 'archived'], true)): ?>
                    <span class="text-2xs text-muted-2 ml-2">— scans show an unavailable page</span>
                    <?php elseif ($qr['status'] === 'disabled'): ?>
                    <span class="text-2xs text-muted-2 ml-2">— contact support</span>
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

<h2 class="mb-3">Actions</h2>
<div class="actions-group">

    <a href="/qr/<?= (int) $qr['id'] ?>/analytics" class="btn btn-secondary">Analytics</a>

    <?php if ($qr['status'] !== 'archived' && $qr['status'] !== 'disabled'): ?>
    <a href="/qr/<?= (int) $qr['id'] ?>/edit" class="btn btn-secondary">Edit</a>
    <?php endif; ?>

    <?php if ($canPauseLinks && $qr['status'] === 'active'): ?>
    <form method="post" action="/qr/<?= (int) $qr['id'] ?>/pause" class="form-inline">
        <?= CsrfService::field() ?>
        <button type="submit" class="btn btn-secondary">Pause</button>
    </form>
    <?php elseif ($canPauseLinks && $qr['status'] === 'paused'): ?>
    <form method="post" action="/qr/<?= (int) $qr['id'] ?>/resume" class="form-inline">
        <?= CsrfService::field() ?>
        <button type="submit" class="btn">Resume</button>
    </form>
    <?php elseif (!$canPauseLinks && ($qr['status'] === 'active' || $qr['status'] === 'paused')): ?>
    <span class="btn-disabled" title="Not available on your plan">Pause / Resume</span>
    <?php endif; ?>

    <?php if (in_array($qr['status'], ['active', 'paused'], true)): ?>
    <form method="post" action="/qr/<?= (int) $qr['id'] ?>/archive" class="form-inline"
          data-confirm="Archive this QR code? It will stop redirecting until restored.">
        <?= CsrfService::field() ?>
        <button type="submit" class="btn btn-secondary text-muted">Archive</button>
    </form>
    <?php elseif ($qr['status'] === 'archived'): ?>
        <?php if ($atQuotaLimit): ?>
        <span class="btn-disabled" title="Active QR code limit reached — archive another to restore this one">Restore</span>
        <?php else: ?>
        <form method="post" action="/qr/<?= (int) $qr['id'] ?>/restore" class="form-inline">
            <?= CsrfService::field() ?>
            <button type="submit" class="btn">Restore</button>
        </form>
        <?php endif; ?>
    <?php endif; ?>

</div>

<h2 class="mb-3">Downloads</h2>
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
<p class="text-2xs text-muted-2 mt-2">
    QR code encodes: <code><?= View::e($shortUrl) ?></code>
</p>

<!-- ── Destination history ─────────────────────────────────────────────────── -->
<h2 class="mt-8 mb-3">Destination History</h2>
<?php
$canRestore = !in_array($qr['status'], ['archived', 'disabled'], true);
$sourceLabels = [
    'system'    => 'Initial destination',
    'user_edit' => 'Edit',
    'restore'   => 'Restore',
];
?>
<?php if (empty($destinationHistory)): ?>
<p class="text-muted-2 text-base">No destination history recorded yet.</p>
<?php else: ?>
<table class="text-85">
    <thead>
        <tr>
            <th class="col-140">When</th>
            <th class="col-90">Source</th>
            <th class="col-160">Changed by</th>
            <th>Previous destination</th>
            <th>New destination</th>
            <?php if ($canRestore): ?><th class="col-80"></th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($destinationHistory as $h): ?>
        <tr>
            <td class="nowrap text-muted"><?= View::e($h['created_at']) ?></td>
            <td><?= View::e($sourceLabels[$h['change_source']] ?? $h['change_source']) ?></td>
            <td class="text-muted">
                <?= $h['changed_by_email'] !== null
                    ? View::e($h['changed_by_email'])
                    : '<span class="text-faint">system</span>' ?>
            </td>
            <td class="word-break text-faint">
                <?= $h['old_target_url'] !== null
                    ? '<a href="' . View::e($h['old_target_url']) . '" target="_blank" class="text-faint">' . View::e($h['old_target_url']) . '</a>'
                    : '—' ?>
            </td>
            <td class="word-break">
                <a href="<?= View::e($h['new_target_url']) ?>" target="_blank">
                    <?= View::e($h['new_target_url']) ?>
                </a>
            </td>
            <?php if ($canRestore): ?>
            <td>
                <?php if ($h['new_target_url'] !== $qr['current_target_url']): ?>
                <form method="post"
                      action="/qr/<?= (int) $qr['id'] ?>/destination-history/<?= (int) $h['id'] ?>/restore"
                      class="form-inline"
                      data-confirm="Restore this destination? The current one will be replaced.">
                    <?= CsrfService::field() ?>
                    <button type="submit" class="btn btn-secondary btn-xs">Restore</button>
                </form>
                <?php else: ?>
                <span class="text-xs text-faint">Current</span>
                <?php endif; ?>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
