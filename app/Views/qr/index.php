<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1.25rem">
    <h1>My QR Codes</h1>
    <a href="/qr/create" class="btn">+ Create QR Code</a>
</div>

<?php if (empty($qrCodes)): ?>
    <p style="color:#666">You haven't created any QR codes yet.</p>
    <p><a href="/qr/create" class="btn">Create your first QR Code</a></p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Slug</th>
            <th>Short URL</th>
            <th>Destination</th>
            <th>Status</th>
            <th>Created</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($qrCodes as $row): ?>
        <tr>
            <td><?= View::e($row['name']) ?></td>
            <td><code><?= View::e($row['slug']) ?></code></td>
            <td>
                <a href="<?= View::e($baseUrl . '/' . $row['slug']) ?>" target="_blank">
                    <?= View::e($baseUrl . '/' . $row['slug']) ?>
                </a>
            </td>
            <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <a href="<?= View::e($row['current_target_url']) ?>" target="_blank" title="<?= View::e($row['current_target_url']) ?>">
                    <?= View::e($row['current_target_url']) ?>
                </a>
            </td>
            <td class="status-<?= View::e($row['status']) ?>"><?= View::e(ucfirst($row['status'])) ?></td>
            <td style="white-space:nowrap"><?= View::e(substr($row['created_at'], 0, 10)) ?></td>
            <td><a href="/qr/<?= (int) $row['id'] ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
