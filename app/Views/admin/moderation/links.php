<div style="display:flex;align-items:baseline;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem">
    <h1>Moderated Links</h1>
    <div style="display:flex;gap:0.6rem">
        <a href="/admin/moderation/domains" class="btn btn-secondary">Blocked Domains</a>
        <a href="/admin" style="color:#666;font-size:0.9rem;align-self:center">&larr; Admin</a>
    </div>
</div>

<?php if ($flash): ?>
<div style="
    background: <?= $flash['type'] === 'success' ? '#f0fdf4' : ($flash['type'] === 'error' ? '#fef2f2' : '#eff6ff') ?>;
    border: 1px solid <?= $flash['type'] === 'success' ? '#86efac' : ($flash['type'] === 'error' ? '#fca5a5' : '#93c5fd') ?>;
    color: <?= $flash['type'] === 'success' ? '#166534' : ($flash['type'] === 'error' ? '#991b1b' : '#1e40af') ?>;
    border-radius: 4px; padding: 0.7rem 1rem; margin-bottom: 1.25rem; font-size: 0.9rem;
"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<!-- ── Filters ─────────────────────────────────────────────────────────────── -->
<form method="get" action="/admin/moderation/links"
      style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1.5rem">
    <div>
        <label for="status" style="display:block;font-size:0.82rem;font-weight:500;color:#555;margin-bottom:0.25rem">Status</label>
        <select id="status" name="status" style="padding:0.4rem 0.6rem;border:1px solid #ccc;border-radius:4px;font-size:0.9rem">
            <option value="disabled"<?= $statusFilter === 'disabled' ? ' selected' : '' ?>>Disabled</option>
            <option value=""<?= $statusFilter === '' ? ' selected' : '' ?>>All statuses</option>
            <option value="active"<?= $statusFilter === 'active' ? ' selected' : '' ?>>Active</option>
            <option value="paused"<?= $statusFilter === 'paused' ? ' selected' : '' ?>>Paused</option>
            <option value="archived"<?= $statusFilter === 'archived' ? ' selected' : '' ?>>Archived</option>
        </select>
    </div>
    <div>
        <label for="owner" style="display:block;font-size:0.82rem;font-weight:500;color:#555;margin-bottom:0.25rem">Owner email</label>
        <input type="text" id="owner" name="owner" value="<?= View::e($ownerFilter) ?>"
               placeholder="@example.com"
               style="padding:0.4rem 0.6rem;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;width:180px">
    </div>
    <div>
        <label for="slug" style="display:block;font-size:0.82rem;font-weight:500;color:#555;margin-bottom:0.25rem">Slug</label>
        <input type="text" id="slug" name="slug" value="<?= View::e($slugFilter) ?>"
               style="padding:0.4rem 0.6rem;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;width:120px">
    </div>
    <div>
        <label for="dest" style="display:block;font-size:0.82rem;font-weight:500;color:#555;margin-bottom:0.25rem">Destination</label>
        <input type="text" id="dest" name="dest" value="<?= View::e($destFilter) ?>"
               style="padding:0.4rem 0.6rem;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;width:200px">
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="/admin/moderation/links" style="font-size:0.85rem;color:#666;align-self:center">Reset</a>
</form>

<!-- ── Results ─────────────────────────────────────────────────────────────── -->
<?php if (empty($links)): ?>
<p style="color:#888">No links match the current filter.</p>
<?php else: ?>
<p style="font-size:0.82rem;color:#888;margin-bottom:0.5rem"><?= count($links) ?> result<?= count($links) === 1 ? '' : 's' ?></p>
<table style="font-size:0.85rem">
    <thead>
        <tr>
            <th style="width:50px">ID</th>
            <th style="width:100px">Slug</th>
            <th>QR Name</th>
            <th>Owner</th>
            <th>Destination</th>
            <th style="width:80px">Status</th>
            <th>Disabled reason</th>
            <th style="width:130px">Disabled at</th>
            <th style="width:50px"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($links as $link): ?>
    <tr>
        <td style="color:#6b7280"><?= (int) $link['id'] ?></td>
        <td><code><?= View::e($link['slug']) ?></code></td>
        <td><?= View::e($link['qr_name'] ?? '—') ?></td>
        <td style="color:#6b7280"><?= View::e($link['owner_email'] ?? '—') ?></td>
        <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <a href="<?= View::e($link['current_target_url']) ?>" target="_blank">
                <?= View::e($link['current_target_url']) ?>
            </a>
        </td>
        <td><span class="status-<?= View::e($link['status']) ?>"><?= View::e(ucfirst($link['status'])) ?></span></td>
        <td style="color:#92400e;font-size:0.82rem"><?= View::e($link['disabled_reason'] ?? '') ?></td>
        <td style="color:#6b7280;font-size:0.82rem;white-space:nowrap"><?= View::e($link['disabled_at'] ?? '') ?></td>
        <td><a href="/admin/moderation/links/<?= (int) $link['id'] ?>" class="btn btn-secondary" style="font-size:0.78rem;padding:0.2rem 0.6rem">Review</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
