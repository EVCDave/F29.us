<div class="page-header">
    <h1>Customize QR Style</h1>
    <a href="/qr/<?= (int) $qr['id'] ?>" class="back-link">&larr; <?= View::e($qr['name']) ?></a>
</div>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<?php if (!$canCustomize): ?>
<div class="card-warn mw-520 mb-6">
    <p class="fw-medium mb-2">Color customization not available on your plan</p>
    <p class="text-88">Upgrade to Starter or higher to customize QR code colors.</p>
    <p class="text-88 mt-3"><a href="/account/subscription">View plans</a></p>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="flash flash-error mb-4">
    <?php foreach ($errors as $e): ?>
    <p><?= View::e($e) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="qr-layout mb-6">

    <?php if ($qrPreviewSvg): ?>
    <div class="qr-preview">
        <img
            src="data:image/svg+xml;base64,<?= $qrPreviewSvg ?>"
            alt="QR code preview for <?= View::e($qr['name']) ?>"
            class="qr-img"
        >
        <?php if ($style['is_custom']): ?>
        <p class="text-2xs text-muted-2 mt-2 text-center">Custom style applied</p>
        <?php else: ?>
        <p class="text-2xs text-muted-2 mt-2 text-center">Default style</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="qr-info">
        <form method="post" action="/qr/<?= (int) $qr['id'] ?>/style">
            <?= CsrfService::field() ?>
            <fieldset <?= !$canCustomize ? 'disabled' : '' ?>>
                <div class="mb-4">
                    <label for="foreground_color">Foreground (dots)</label>
                    <div class="d-flex align-center gap-2">
                        <input
                            type="color"
                            id="foreground_color"
                            name="foreground_color"
                            value="<?= View::e($style['foreground_color'] ?? '#000000') ?>"
                            class="color-swatch"
                        >
                        <span class="text-sm text-muted-2"><?= View::e($style['foreground_color'] ?? '#000000') ?></span>
                    </div>
                </div>
                <div class="mb-5">
                    <label for="background_color">Background</label>
                    <div class="d-flex align-center gap-2">
                        <input
                            type="color"
                            id="background_color"
                            name="background_color"
                            value="<?= View::e($style['background_color'] ?? '#FFFFFF') ?>"
                            class="color-swatch"
                        >
                        <span class="text-sm text-muted-2"><?= View::e($style['background_color'] ?? '#FFFFFF') ?></span>
                    </div>
                </div>
                <button type="submit" class="btn">Save Colors</button>
            </fieldset>
        </form>
    </div>

</div>

<?php if ($canCustomize && $style['is_custom']): ?>
<form method="post" action="/qr/<?= (int) $qr['id'] ?>/style/reset" class="form-inline mb-6"
      data-confirm="Reset to default black-on-white colors?">
    <?= CsrfService::field() ?>
    <button type="submit" class="btn btn-secondary text-muted">Reset to Default</button>
</form>
<?php endif; ?>

<div class="card-note mw-520">
    <p class="fw-medium mb-2">Color tips</p>
    <ul class="text-88 mt-1 mb-0">
        <li>Use high-contrast colors for reliable scanning. Dark foreground on light background works best.</li>
        <li>Avoid light-on-light or dark-on-dark combinations — scanners may fail in poor lighting.</li>
        <li>Custom colors automatically use increased error correction for better resilience.</li>
    </ul>
    <?php if ($canCustomize): ?>
    <p class="text-88 mt-3 text-muted-2">
        Logo upload — coming soon for Pro and Team plans.
    </p>
    <?php endif; ?>
</div>
