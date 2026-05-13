<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1.25rem">
    <h1>My QR Codes</h1>
    <a href="/qr/create" class="btn">+ Create QR Code</a>
</div>

<?php if ($flash): ?>
<div class="notice" style="display:block;margin-bottom:1.25rem;
    <?= $flash['type'] === 'error'   ? 'background:#fef2f2;border-color:#fca5a5;color:#991b1b;' : '' ?>
    <?= $flash['type'] === 'success' ? 'background:#f0fdf4;border-color:#86efac;color:#166534;' : '' ?>
    <?= $flash['type'] === 'info'    ? 'background:#eff6ff;border-color:#93c5fd;color:#1e40af;' : '' ?>">
    <?= View::e($flash['text']) ?>
</div>
<?php endif; ?>

<!-- ── Filter form ─────────────────────────────────────────────────────────── -->
<form method="get" action="/qr" style="display:flex;gap:0.6rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1.5rem">
    <div>
        <label for="search" style="display:block;font-size:0.82rem;font-weight:500;color:#555;margin-bottom:0.25rem">Search</label>
        <input
            type="text"
            id="search"
            name="search"
            value="<?= View::e($search) ?>"
            placeholder="Name, slug, or destination"
            style="width:240px;max-width:100%"
        >
    </div>
    <div>
        <label for="status" style="display:block;font-size:0.82rem;font-weight:500;color:#555;margin-bottom:0.25rem">Status</label>
        <select id="status" name="status" style="padding:0.45rem 0.65rem;border:1px solid #ccc;border-radius:4px;font-size:0.95rem">
            <option value="" <?= $status === '' ? 'selected' : '' ?>>All</option>
            <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
            <option value="paused"   <?= $status === 'paused'   ? 'selected' : '' ?>>Paused</option>
            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
            <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>Disabled</option>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <?php if ($search !== '' || $status !== ''): ?>
    <a href="/qr" style="font-size:0.85rem;color:#666;align-self:center">Clear</a>
    <?php endif; ?>
</form>

<?php if (empty($qrCodes) && $search === '' && $status === ''): ?>
    <p style="color:#666">You haven't created any QR codes yet.</p>
    <p><a href="/qr/create" class="btn">Create your first QR Code</a></p>
<?php elseif (empty($qrCodes)): ?>
    <p style="color:#888">No QR codes match your filter. <a href="/qr">Clear filters</a> to see all.</p>
<?php else: ?>
<?php if (count($qrCodes) >= 100): ?>
<p style="color:#888;font-size:0.85rem;margin-bottom:0.75rem">Showing 100 results (capped). Use search to narrow.</p>
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
            <td style="white-space:nowrap">
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
