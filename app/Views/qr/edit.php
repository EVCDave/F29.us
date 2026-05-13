<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1rem">
    <h1>Edit QR Code</h1>
    <a href="/qr/<?= (int) $qr['id'] ?>" style="color:#666;font-size:0.9rem">&larr; <?= View::e($qr['name']) ?></a>
</div>

<?php if ($canEditDestination): ?>
<p style="color:#666;margin-bottom:1.5rem">
    Changing the destination does not affect your QR code or slug —
    the short URL <strong><?= View::e($qr['slug']) ?></strong> stays the same.
</p>
<?php else: ?>
<p style="color:#666;margin-bottom:1.5rem">
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
            style="max-width:520px"
            required
        >
        <p style="font-size:0.8rem;color:#888;margin-top:0.25rem;margin-bottom:0">
            Internal label — not visible to people who scan the code.
        </p>
    </div>

    <?php if ($canEditDestination): ?>
    <div class="form-group" style="margin-top:1.25rem">
        <label for="destination_url">Destination URL</label>
        <input
            type="text"
            id="destination_url"
            name="destination_url"
            value="<?= View::e($oldUrl) ?>"
            maxlength="2048"
            style="max-width:520px"
            required
        >
    </div>
    <?php endif; ?>

    <div class="form-group" style="margin-top:1.25rem">
        <button type="submit" class="btn">Save Changes</button>
        <a href="/qr/<?= (int) $qr['id'] ?>" style="margin-left:1rem;color:#666">Cancel</a>
    </div>

</form>
