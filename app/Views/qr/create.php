<h1>Create a QR Code</h1>

<?php if ($limitReached): ?>
<div class="card-warn mw-520">
    <p class="fw-medium mb-2">Active QR code limit reached</p>
    <p class="text-88">You have reached your plan limit of <?= (int) $maxQr ?> active QR
    code<?= $maxQr !== 1 ? 's' : '' ?>. Archive an existing QR code to free up capacity, or upgrade your plan to create more.</p>
    <p class="text-88 mt-3">
        <a href="/qr?status=active">View active QR codes</a> &bull;
        <a href="/account/subscription">Subscription</a>
    </p>
</div>
<?php else: ?>

<?php if (!empty($errors)): ?>
<ul class="errors">
    <?php foreach ($errors as $e): ?>
    <li><?= View::e($e) ?></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/qr">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label for="name">QR Code Name</label>
        <input
            type="text"
            id="name"
            name="name"
            value="<?= View::e($oldValues['name'] ?? '') ?>"
            placeholder="e.g. Office door, Product label"
            maxlength="200"
            required
        >
        <small class="text-muted-3">Used only for your reference — not shown publicly.</small>
    </div>

    <div class="form-group">
        <label for="destination_url">Destination URL</label>
        <input
            type="text"
            id="destination_url"
            name="destination_url"
            value="<?= View::e($oldValues['destUrl'] ?? '') ?>"
            placeholder="https://example.com/your-page"
            maxlength="2048"
            class="mw-520"
            required
        >
        <small class="text-muted-3">Where the QR code will send visitors. You can change this later.</small>
    </div>

    <div class="form-group">
        <label for="custom_slug">
            Custom Slug
            <?php if (!$canCustomSlug): ?>
            <small class="text-muted-2 fw-normal">&mdash; not available on your plan</small>
            <?php endif; ?>
        </label>
        <input
            type="text"
            id="custom_slug"
            name="custom_slug"
            value="<?= View::e($oldValues['customSlug'] ?? '') ?>"
            placeholder="<?= $canCustomSlug ? 'e.g. summer-sale' : 'Auto-generated' ?>"
            <?php if (!$canCustomSlug): ?>disabled<?php endif; ?>
        >
        <?php if (!$canCustomSlug): ?>
        <small class="text-muted-3">Leave blank — a unique slug will be generated automatically. Upgrade to choose a custom slug.</small>
        <?php else: ?>
        <small class="text-muted-3">Lowercase letters, numbers, and hyphens only. Leave blank for auto-generation.</small>
        <?php endif; ?>
    </div>

    <div class="form-group mt-6">
        <button type="submit" class="btn">Create QR Code</button>
        <a href="/qr" class="cancel-link">Cancel</a>
    </div>

</form>

<?php endif; ?>
