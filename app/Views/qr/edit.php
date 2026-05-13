<h1>Edit Destination</h1>
<p style="color:#666;margin-bottom:1.5rem">
    Changing the destination does not affect your QR code or slug —
    the short URL <strong><?= View::e($qr['slug']) ?></strong> stays the same.
</p>

<?php if (!empty($errors)): ?>
<ul class="errors">
    <?php foreach ($errors as $e): ?>
    <li><?= View::e($e) ?></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/qr/<?= (int) $qr['id'] ?>/update">

    <div class="form-group">
        <label for="destination_url">New Destination URL</label>
        <input
            type="text"
            id="destination_url"
            name="destination_url"
            value="<?= View::e($oldUrl) ?>"
            style="max-width:520px"
            required
        >
    </div>

    <div class="form-group" style="margin-top:1.25rem">
        <button type="submit" class="btn">Save Destination</button>
        <a href="/qr/<?= (int) $qr['id'] ?>" style="margin-left:1rem;color:#666">Cancel</a>
    </div>

</form>
