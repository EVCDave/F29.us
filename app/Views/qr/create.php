<h1>Create a QR Code</h1>

<?php if ($limitReached): ?>
    <p class="notice">
        You have reached the <?= (int) $maxQr ?> QR code limit for your plan.
        Upgrade your plan to create more.
    </p>
    <p><a href="/qr">&larr; Back to My QR Codes</a></p>
<?php else: ?>

<?php if (!empty($errors)): ?>
<ul class="errors">
    <?php foreach ($errors as $e): ?>
    <li><?= View::e($e) ?></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/qr">

    <div class="form-group">
        <label for="name">QR Code Name</label>
        <input
            type="text"
            id="name"
            name="name"
            value="<?= View::e($oldValues['name'] ?? '') ?>"
            placeholder="e.g. Office door, Product label"
            required
        >
        <small style="color:#666">Used only for your reference — not shown publicly.</small>
    </div>

    <div class="form-group">
        <label for="destination_url">Destination URL</label>
        <input
            type="text"
            id="destination_url"
            name="destination_url"
            value="<?= View::e($oldValues['destUrl'] ?? '') ?>"
            placeholder="https://example.com/your-page"
            style="max-width:520px"
            required
        >
        <small style="color:#666">Where the QR code will send visitors. You can change this later.</small>
    </div>

    <div class="form-group">
        <label for="custom_slug">
            Custom Slug
            <?php if (!$canCustomSlug): ?>
            <small style="color:#888;font-weight:400">&mdash; not available on your plan</small>
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
        <small style="color:#666">Leave blank — a unique slug will be generated automatically. Upgrade to choose a custom slug.</small>
        <?php else: ?>
        <small style="color:#666">Lowercase letters, numbers, and hyphens only. Leave blank for auto-generation.</small>
        <?php endif; ?>
    </div>

    <div class="form-group" style="margin-top:1.5rem">
        <button type="submit" class="btn">Create QR Code</button>
        <a href="/qr" style="margin-left:1rem;color:#666">Cancel</a>
    </div>

</form>

<?php endif; ?>
