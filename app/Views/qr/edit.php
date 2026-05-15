<div class="page-header">
    <h1>Edit QR Code</h1>
    <a href="/qr/<?= (int) $qr['id'] ?>" class="back-link">&larr; <?= View::e($qr['name']) ?></a>
</div>

<?php if ($canEditDestination): ?>
<p class="text-muted-3 mb-6">
    Changing the destination does not affect your QR code or slug —
    the short URL <strong><?= View::e($qr['slug']) ?></strong> stays the same.
</p>
<?php else: ?>
<p class="text-muted-3 mb-6">
    You can update the QR code name. Destination editing is not available on your plan.
</p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<ul class="errors">
    <?php foreach ($errors as $e): ?>
    <li><?= View::e($e) ?></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/qr/<?= (int) $qr['id'] ?>/update">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label for="name">Name</label>
        <input
            type="text"
            id="name"
            name="name"
            value="<?= View::e($oldName) ?>"
            maxlength="200"
            class="mw-520"
            required
        >
        <p class="hint">Internal label — not visible to people who scan the code.</p>
    </div>

    <?php if ($canEditDestination): ?>
    <div class="form-group mt-5">
        <label for="destination_url">Destination URL</label>
        <input
            type="text"
            id="destination_url"
            name="destination_url"
            value="<?= View::e($oldUrl) ?>"
            maxlength="2048"
            class="mw-520"
            required
        >
    </div>
    <?php endif; ?>

    <div class="form-group mt-5">
        <button type="submit" class="btn">Save Changes</button>
        <a href="/qr/<?= (int) $qr['id'] ?>" class="cancel-link">Cancel</a>
    </div>

</form>
