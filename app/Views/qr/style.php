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

<!-- ── QR Preview + Color Picker ─────────────────────────────────────────── -->
<div class="qr-layout mb-6">

    <?php if ($qrPreviewSvg): ?>
    <div class="qr-preview<?= ($style['background_transparent'] ?? false) ? ' qr-preview-transparent' : '' ?>">
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
                <div class="mb-4">
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
                <div class="mb-5">
                    <label class="checkbox-label">
                        <input
                            type="checkbox"
                            name="background_transparent"
                            <?= ($style['background_transparent'] ?? false) ? 'checked' : '' ?>
                        >
                        Use transparent background
                    </label>
                    <p class="text-2xs text-muted-2 mt-1">
                        Transparent QR codes must be placed on a light, high-contrast background.
                        Always test before printing.
                    </p>
                </div>
                <button type="submit" class="btn">Save Colors</button>
            </fieldset>
        </form>
    </div>

</div>

<?php if ($canCustomize && $style['is_custom'] && !$style['logo_enabled']): ?>
<form method="post" action="/qr/<?= (int) $qr['id'] ?>/style/reset" class="form-inline mb-6"
      data-confirm="Reset to default black-on-white colors?">
    <?= CsrfService::field() ?>
    <button type="submit" class="btn btn-secondary text-muted">Reset to Default</button>
</form>
<?php elseif ($canCustomize && $style['is_custom'] && $style['logo_enabled']): ?>
<form method="post" action="/qr/<?= (int) $qr['id'] ?>/style/reset" class="form-inline mb-6"
      data-confirm="Remove logo and reset all style settings to defaults?">
    <?= CsrfService::field() ?>
    <button type="submit" class="btn btn-secondary text-muted">Reset All to Default</button>
</form>
<?php endif; ?>

<!-- ── Logo Upload ────────────────────────────────────────────────────────── -->
<h2 class="mb-3">Logo</h2>

<?php if (!$canUploadLogo): ?>
<div class="card-note mw-520 mb-6">
    <p class="fw-medium mb-2">Logo in QR code</p>
    <p class="text-88">Available on Pro and Team plans. <a href="/account/subscription">View plans</a></p>
</div>
<?php else: ?>

<?php if ($style['logo_enabled'] && $style['logo_original_filename']): ?>
<div class="card-note mw-520 mb-4">
    <p class="fw-medium mb-1">Current logo</p>
    <p class="text-88"><?= View::e($style['logo_original_filename']) ?></p>
    <?php if ($style['logo_size_bytes']): ?>
    <p class="text-2xs text-muted-2 mt-1"><?= number_format((int) $style['logo_size_bytes'] / 1024, 1) ?> KB</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="mw-480 mb-6">
    <form method="post" action="/qr/<?= (int) $qr['id'] ?>/style/logo" enctype="multipart/form-data">
        <?= CsrfService::field() ?>
        <div class="mb-3">
            <label for="logo">
                <?= $style['logo_enabled'] ? 'Replace logo' : 'Upload logo' ?>
            </label>
            <p class="text-2xs text-muted-2 mb-2">
                PNG, JPG, or WEBP &mdash; max <?= (int) $logoMaxKb ?> KB &mdash; logo appears at <?= (int) $logoMaxPercent ?>% of QR width
            </p>
            <input type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,.webp">
        </div>
        <button type="submit" class="btn">Upload Logo</button>
    </form>
</div>

<?php if ($style['logo_enabled']): ?>
<form method="post" action="/qr/<?= (int) $qr['id'] ?>/style/logo/remove" class="form-inline mb-6"
      data-confirm="Remove the logo from this QR code?">
    <?= CsrfService::field() ?>
    <button type="submit" class="btn btn-secondary text-muted">Remove Logo</button>
</form>
<?php endif; ?>

<div class="card-note mw-520 mb-6">
    <p class="fw-medium mb-2">Logo tips</p>
    <ul class="text-88 mt-1 mb-0">
        <li>Use a simple, high-contrast logo on a white or light background for best results.</li>
        <li>Keep the logo small and avoid fine details — QR code resolution is limited.</li>
        <li>Always test scanning after uploading a logo, especially in lower-light conditions.</li>
        <li>Logo-enabled QR codes use the highest error correction level (H) automatically.</li>
    </ul>
</div>

<?php endif; ?>

<!-- ── Color tips ─────────────────────────────────────────────────────────── -->
<div class="card-note mw-520">
    <p class="fw-medium mb-2">Color tips</p>
    <ul class="text-88 mt-1 mb-0">
        <li>Use high-contrast colors for reliable scanning. Dark foreground on light background works best.</li>
        <li>Avoid light-on-light or dark-on-dark combinations — scanners may fail in poor lighting.</li>
        <li>Custom colors automatically use increased error correction for better resilience.</li>
    </ul>
</div>
