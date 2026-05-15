<div class="page-header">
    <h1>Moderated Links</h1>
    <div class="d-flex gap-2 align-center">
        <a href="/admin/moderation/domains" class="btn btn-secondary">Blocked Domains</a>
        <a href="/admin" class="back-link">&larr; Admin</a>
    </div>
</div>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<!-- ── Filters ─────────────────────────────────────────────────────────────── -->
<form method="get" action="/admin/moderation/links"
      class="d-flex gap-3 align-end flex-wrap mb-6">
    <div>
        <label for="status" class="filter-label">Status</label>
        <select id="status" name="status" class="filter-input">
            <option value="disabled"<?= $statusFilter === 'disabled' ? ' selected' : '' ?>>Disabled</option>
            <option value=""<?= $statusFilter === '' ? ' selected' : '' ?>>All statuses</option>
            <option value="active"<?= $statusFilter === 'active' ? ' selected' : '' ?>>Active</option>
            <option value="paused"<?= $statusFilter === 'paused' ? ' selected' : '' ?>>Paused</option>
            <option value="archived"<?= $statusFilter === 'archived' ? ' selected' : '' ?>>Archived</option>
        </select>
    </div>
    <div>
        <label for="owner" class="filter-label">Owner email</label>
        <input type="text" id="owner" name="owner" value="<?= View::e($ownerFilter) ?>"
               placeholder="@example.com" class="filter-input">
    </div>
    <div>
        <label for="slug" class="filter-label">Slug</label>
        <input type="text" id="slug" name="slug" value="<?= View::e($slugFilter) ?>"
               class="filter-input">
    </div>
    <div>
        <label for="dest" class="filter-label">Destination</label>
        <input type="text" id="dest" name="dest" value="<?= View::e($destFilter) ?>"
               class="filter-input">
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="/admin/moderation/links" class="filter-link">Reset</a>
</form>

<!-- ── Results ─────────────────────────────────────────────────────────────── -->
<?php if (empty($links)): ?>
<p class="text-muted-2">No links match the current filter.</p>
<?php else: ?>
<p class="text-82 text-muted-2 mb-2"><?= count($links) ?> result<?= count($links) === 1 ? '' : 's' ?></p>
<table class="text-sm">
    <thead>
        <tr>
            <th class="col-50">ID</th>
            <th class="col-100">Slug</th>
            <th>QR Name</th>
            <th>Owner</th>
            <th>Destination</th>
            <th class="col-80">Status</th>
            <th>Disabled reason</th>
            <th class="col-130">Disabled at</th>
            <th class="col-50"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($links as $link): ?>
    <tr>
        <td class="text-muted"><?= (int) $link['id'] ?></td>
        <td><code><?= View::e($link['slug']) ?></code></td>
        <td><?= View::e($link['qr_name'] ?? '—') ?></td>
        <td class="text-muted"><?= View::e($link['owner_email'] ?? '—') ?></td>
        <td class="col-220 text-truncate">
            <a href="<?= View::e($link['current_target_url']) ?>" target="_blank">
                <?= View::e($link['current_target_url']) ?>
            </a>
        </td>
        <td><span class="status-<?= View::e($link['status']) ?>"><?= View::e(ucfirst($link['status'])) ?></span></td>
        <td class="text-warning text-82"><?= View::e($link['disabled_reason'] ?? '') ?></td>
        <td class="text-muted text-82 nowrap"><?= View::e($link['disabled_at'] ?? '') ?></td>
        <td><a href="/admin/moderation/links/<?= (int) $link['id'] ?>" class="btn btn-secondary btn-xs">Review</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
