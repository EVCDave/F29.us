<div class="page-header page-header-lg">
    <h1>My QR Codes</h1>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/qr/create" class="btn">+ Create QR Code</a>
        <a href="/qr/static" class="btn btn-secondary">Create Static QR</a>
    </div>
</div>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<!-- ── Filter form ─────────────────────────────────────────────────────────── -->
<form method="get" action="/qr" class="filter-form">
    <div>
        <label for="search" class="filter-label">Search</label>
        <input
            type="text"
            id="search"
            name="search"
            value="<?= View::e($search) ?>"
            placeholder="Name, slug, or destination"
            class="mw-240"
        >
    </div>
    <div>
        <label for="status" class="filter-label">Status</label>
        <select id="status" name="status">
            <option value="" <?= $status === '' ? 'selected' : '' ?>>All</option>
            <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
            <option value="paused"   <?= $status === 'paused'   ? 'selected' : '' ?>>Paused</option>
            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
            <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>Disabled</option>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <?php if ($search !== '' || $status !== ''): ?>
    <a href="/qr" class="filter-link">Clear</a>
    <?php endif; ?>
</form>

<?php if ($status === 'archived'): ?>
<p class="text-sm text-muted-2 mb-4">Archived QR codes are retained for history but do not count against your active QR limit.</p>
<?php endif; ?>

<?php if (empty($qrCodes) && $search === '' && $status === ''): ?>
    <p class="text-muted-3">You haven't created any QR codes yet.</p>
    <p><a href="/qr/create" class="btn">Create your first QR Code</a></p>
<?php elseif (empty($qrCodes)): ?>
    <p class="text-muted-2">No QR codes match your filter. <a href="/qr">Clear filters</a> to see all.</p>
<?php else: ?>
<?php if (count($qrCodes) >= 100): ?>
<p class="text-muted-2 text-sm mb-3">Showing 100 results (capped). Use search to narrow.</p>
<?php endif; ?>
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
            <td class="nowrap">
                <a href="<?= View::e($baseUrl . '/' . $row['slug']) ?>" target="_blank">
                    <?= View::e($baseUrl . '/' . $row['slug']) ?>
                </a>
            </td>
            <td class="text-ellipsis mw-240">
                <a href="<?= View::e($row['current_target_url']) ?>" target="_blank" title="<?= View::e($row['current_target_url']) ?>">
                    <?= View::e($row['current_target_url']) ?>
                </a>
            </td>
            <td class="status-<?= View::e($row['status']) ?>"><?= View::e(ucfirst($row['status'])) ?></td>
            <td class="nowrap"><?= View::e(substr($row['created_at'], 0, 10)) ?></td>
            <td><a href="/qr/<?= (int) $row['id'] ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
