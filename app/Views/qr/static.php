<?php
$type = $input['type'] ?? 'text';
$ok   = ($result['ok'] ?? false) === true;

/** Build the hidden inputs that re-submit the validated state from preview → download. */
$hiddenInputs = static function (array $input): string {
    $passthrough = [
        'type', 'content',
        'ssid', 'password', 'security',
        'email_to', 'email_subject', 'email_body',
        'first_name', 'last_name', 'display_name',
        'company', 'title', 'phone', 'email', 'website',
        'foreground_color', 'background_color', 'module_style',
    ];
    $html = '';
    foreach ($passthrough as $key) {
        $value = $input[$key] ?? '';
        if (!is_scalar($value)) continue;
        $html .= '<input type="hidden" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8')
              . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '">';
    }
    if (!empty($input['hidden'])) {
        $html .= '<input type="hidden" name="hidden" value="1">';
    }
    if (!empty($input['background_transparent'])) {
        $html .= '<input type="hidden" name="background_transparent" value="1">';
    }
    return $html;
};
?>
<div class="page-header page-header-lg">
    <h1>Static QR Generator</h1>
    <a href="/qr" class="back-link">&larr; My QR Codes</a>
</div>

<div class="card-note mw-720 mb-6">
    <p class="fw-medium mb-2">Static QR codes are not saved to your account.</p>
    <p class="text-88 mb-2">
        Download the file after generating it. If you need a QR code you can edit later,
        <a href="/qr/create">create a dynamic QR code</a> instead.
    </p>
    <p class="text-2xs text-muted-2 mb-0">
        Static QR codes do not collect scan analytics and cannot be paused, archived, or redirected.
    </p>
</div>

<?php if (!empty($errors)): ?>
<div class="flash flash-error mb-4">
    <?php foreach ($errors as $e): ?>
    <p><?= View::e($e) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" action="/qr/static/preview">
    <?= CsrfService::field() ?>

    <h2 class="mb-3">1. Choose a template</h2>
    <div class="mb-5 d-flex gap-2 flex-wrap">
        <?php
        $typeLabels = ['text' => 'Text / URL', 'wifi' => 'Wi-Fi', 'email' => 'Email', 'vcard' => 'vCard'];
        foreach ($typeLabels as $value => $label):
        ?>
        <label class="checkbox-label">
            <input type="radio" name="type" value="<?= View::e($value) ?>"
                   <?= $type === $value ? 'checked' : '' ?>>
            <?= View::e($label) ?>
        </label>
        <?php endforeach; ?>
    </div>

    <h2 class="mb-3">2. Fill in the fields for your chosen template</h2>
    <p class="text-2xs text-muted-2 mb-4">
        Only the fields under the template you selected above are used. The others are ignored.
    </p>

    <!-- ── Text / URL ───────────────────────────────────────────────────── -->
    <fieldset class="mw-720 mb-6">
        <legend><strong>Text / URL</strong></legend>
        <div class="mb-3">
            <label for="content">Content</label>
            <textarea id="content" name="content" rows="3"
                      maxlength="1200"
                      placeholder="https://example.com or any plain text"><?= View::e((string) ($input['content'] ?? '')) ?></textarea>
            <p class="text-2xs text-muted-2 mt-1">Up to 1200 characters.</p>
        </div>
    </fieldset>

    <!-- ── Wi-Fi ────────────────────────────────────────────────────────── -->
    <fieldset class="mw-720 mb-6">
        <legend><strong>Wi-Fi</strong></legend>
        <div class="mb-3">
            <label for="ssid">Network name (SSID)</label>
            <input type="text" id="ssid" name="ssid" maxlength="128"
                   value="<?= View::e((string) ($input['ssid'] ?? '')) ?>">
        </div>
        <div class="mb-3">
            <label for="security">Security</label>
            <?php $sec = (string) ($input['security'] ?? 'WPA'); ?>
            <select id="security" name="security">
                <option value="WPA"    <?= $sec === 'WPA'    ? 'selected' : '' ?>>WPA / WPA2</option>
                <option value="WEP"    <?= $sec === 'WEP'    ? 'selected' : '' ?>>WEP</option>
                <option value="nopass" <?= $sec === 'nopass' ? 'selected' : '' ?>>None (open network)</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="password">Password</label>
            <input type="text" id="password" name="password" maxlength="256"
                   value="<?= View::e((string) ($input['password'] ?? '')) ?>">
            <p class="text-2xs text-muted-2 mt-1">Required for WPA/WEP. Leave blank for open networks.</p>
        </div>
        <div class="mb-3">
            <label class="checkbox-label">
                <input type="checkbox" name="hidden" value="1"
                       <?= !empty($input['hidden']) ? 'checked' : '' ?>>
                This is a hidden network
            </label>
        </div>
    </fieldset>

    <!-- ── Email ────────────────────────────────────────────────────────── -->
    <fieldset class="mw-720 mb-6">
        <legend><strong>Email</strong></legend>
        <div class="mb-3">
            <label for="email_to">Recipient email</label>
            <input type="email" id="email_to" name="email_to" maxlength="255"
                   value="<?= View::e((string) ($input['email_to'] ?? '')) ?>">
        </div>
        <div class="mb-3">
            <label for="email_subject">Subject (optional)</label>
            <input type="text" id="email_subject" name="email_subject" maxlength="200"
                   value="<?= View::e((string) ($input['email_subject'] ?? '')) ?>">
        </div>
        <div class="mb-3">
            <label for="email_body">Body (optional)</label>
            <textarea id="email_body" name="email_body" rows="3"
                      maxlength="1000"><?= View::e((string) ($input['email_body'] ?? '')) ?></textarea>
        </div>
    </fieldset>

    <!-- ── vCard ────────────────────────────────────────────────────────── -->
    <fieldset class="mw-720 mb-6">
        <legend><strong>vCard (contact card)</strong></legend>
        <p class="text-2xs text-muted-2 mb-3">Fill in at least one field.</p>
        <div class="mb-3 d-flex gap-2 flex-wrap">
            <div class="flex-1">
                <label for="first_name">First name</label>
                <input type="text" id="first_name" name="first_name" maxlength="100"
                       value="<?= View::e((string) ($input['first_name'] ?? '')) ?>">
            </div>
            <div class="flex-1">
                <label for="last_name">Last name</label>
                <input type="text" id="last_name" name="last_name" maxlength="100"
                       value="<?= View::e((string) ($input['last_name'] ?? '')) ?>">
            </div>
        </div>
        <div class="mb-3">
            <label for="display_name">Display name (optional)</label>
            <input type="text" id="display_name" name="display_name" maxlength="200"
                   value="<?= View::e((string) ($input['display_name'] ?? '')) ?>">
        </div>
        <div class="mb-3 d-flex gap-2 flex-wrap">
            <div class="flex-1">
                <label for="company">Company</label>
                <input type="text" id="company" name="company" maxlength="200"
                       value="<?= View::e((string) ($input['company'] ?? '')) ?>">
            </div>
            <div class="flex-1">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" maxlength="200"
                       value="<?= View::e((string) ($input['title'] ?? '')) ?>">
            </div>
        </div>
        <div class="mb-3">
            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" maxlength="50"
                   value="<?= View::e((string) ($input['phone'] ?? '')) ?>">
        </div>
        <div class="mb-3">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="255"
                   value="<?= View::e((string) ($input['email'] ?? '')) ?>">
        </div>
        <div class="mb-3">
            <label for="website">Website</label>
            <input type="url" id="website" name="website" maxlength="2048"
                   placeholder="https://example.com"
                   value="<?= View::e((string) ($input['website'] ?? '')) ?>">
        </div>
    </fieldset>

    <!-- ── Style ────────────────────────────────────────────────────────── -->
    <h2 class="mb-3">3. Style</h2>
    <fieldset class="mw-720 mb-6" <?= !$canColors ? 'disabled' : '' ?>>
        <legend><strong>Colors and background</strong></legend>
        <?php if (!$canColors): ?>
        <p class="text-88 text-muted-2 mb-3">
            Custom colors and transparent background are available on Starter and higher plans.
        </p>
        <?php endif; ?>
        <div class="mb-3 d-flex gap-2 flex-wrap">
            <div>
                <label for="foreground_color">Foreground</label>
                <input type="color" id="foreground_color" name="foreground_color"
                       value="<?= View::e((string) ($input['foreground_color'] ?? '#000000')) ?>"
                       class="color-swatch">
            </div>
            <div>
                <label for="background_color">Background</label>
                <input type="color" id="background_color" name="background_color"
                       value="<?= View::e((string) ($input['background_color'] ?? '#FFFFFF')) ?>"
                       class="color-swatch">
            </div>
        </div>
        <div>
            <label class="checkbox-label">
                <input type="checkbox" name="background_transparent" value="1"
                       <?= !empty($input['background_transparent']) ? 'checked' : '' ?>>
                Use transparent background
            </label>
        </div>
    </fieldset>

    <fieldset class="mw-720 mb-6" <?= !$canModuleStyle ? 'disabled' : '' ?>>
        <legend><strong>Module style</strong></legend>
        <?php if (!$canModuleStyle): ?>
        <p class="text-88 text-muted-2 mb-3">
            Module styles are available on Starter and higher plans.
        </p>
        <?php endif; ?>
        <?php $mod = (string) ($input['module_style'] ?? 'square'); ?>
        <select name="module_style">
            <option value="square"        <?= $mod === 'square'        ? 'selected' : '' ?>>Classic squares &mdash; Most compatible</option>
            <option value="gapped_square" <?= $mod === 'gapped_square' ? 'selected' : '' ?>>Gapped squares &mdash; Modern look with spacing</option>
            <option value="circle"        <?= $mod === 'circle'        ? 'selected' : '' ?>>Circles &mdash; Rounded dot style</option>
        </select>
        <?php if (!$canModuleStyle): ?>
        <input type="hidden" name="module_style" value="square">
        <?php endif; ?>
    </fieldset>

    <button type="submit" class="btn">Preview Static QR</button>
</form>

<?php if ($previewSvg && $ok): ?>
<!-- ── Preview + downloads ─────────────────────────────────────────────── -->
<h2 class="mt-8 mb-3">Preview</h2>
<div class="qr-layout mb-4">
    <div class="qr-preview<?= !empty($style['background_transparent']) ? ' qr-preview-transparent' : '' ?>">
        <img src="data:image/svg+xml;base64,<?= $previewSvg ?>"
             alt="Static QR preview"
             class="qr-img">
        <p class="text-2xs text-muted-2 mt-2 text-center"><?= View::e($result['label']) ?></p>
    </div>
    <div class="qr-info">
        <p class="text-88 mb-3">
            Looks good? Download the file below. Once downloaded, the QR code is permanent &mdash;
            f29 cannot change its destination later.
        </p>
        <p class="text-2xs text-muted-2 mb-0">
            Encoded payload length: <?= strlen($result['payload']) ?> characters
            (max <?= StaticQrPayloadService::MAX_PAYLOAD_LENGTH ?>).
        </p>
    </div>
</div>

<h2 class="mb-3">Downloads</h2>
<div class="actions-group">
    <?php if ($canExportPng): ?>
    <form method="post" action="/qr/static/download/png" class="qr-download-form">
        <?= CsrfService::field() ?>
        <?= $hiddenInputs($input) ?>
        <label for="png-size" class="sr-only">PNG size</label>
        <select id="png-size" name="size" aria-label="PNG size">
            <?php foreach ($pngDownloadSizes as $sizeOption): ?>
            <option value="<?= (int) $sizeOption ?>"
                <?= (int) $sizeOption === $pngSelectedSize ? 'selected' : '' ?>>
                <?= (int) $sizeOption ?>px
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary">Download PNG</button>
    </form>
    <?php else: ?>
    <span class="btn-disabled" title="Not available on your plan">Download PNG</span>
    <?php endif; ?>

    <?php if ($canExportSvg): ?>
    <form method="post" action="/qr/static/download/svg" class="form-inline">
        <?= CsrfService::field() ?>
        <?= $hiddenInputs($input) ?>
        <button type="submit" class="btn btn-secondary">Download SVG</button>
    </form>
    <?php else: ?>
    <span class="btn-disabled" title="SVG export not available on your plan">Download SVG</span>
    <?php endif; ?>
</div>
<p class="text-2xs text-muted-2 mt-2">
    SVG is vector-based and can be scaled without quality loss.
</p>
<?php endif; ?>
