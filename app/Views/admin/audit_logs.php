<div class="page-header">
    <h1>Audit Logs</h1>
    <a href="/admin" class="back-link">&larr; Admin</a>
</div>

<!-- ── Filters ─────────────────────────────────────────────────────────────── -->
<form method="get" action="/admin/audit-logs" class="filter-panel mw-860">
    <div class="filter-panel-grid">
        <div>
            <label class="filter-label">Action</label>
            <input type="text" name="action" value="<?= View::e($action) ?>"
                   placeholder="e.g. plan_created" class="filter-input">
        </div>
        <div>
            <label class="filter-label">Entity Type</label>
            <input type="text" name="entity_type" value="<?= View::e($entityType) ?>"
                   placeholder="e.g. plan" class="filter-input">
        </div>
        <div>
            <label class="filter-label">User Email</label>
            <input type="text" name="user_email" value="<?= View::e($userEmail) ?>"
                   placeholder="partial match" class="filter-input">
        </div>
        <div>
            <label class="filter-label">Date From</label>
            <input type="date" name="date_from" value="<?= View::e($dateFrom) ?>" class="filter-input">
        </div>
        <div>
            <label class="filter-label">Date To</label>
            <input type="date" name="date_to" value="<?= View::e($dateTo) ?>" class="filter-input">
        </div>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-sm">Filter</button>
        <a href="/admin/audit-logs" class="btn btn-secondary btn-sm">Clear</a>
    </div>
</form>

<!-- ── Results ─────────────────────────────────────────────────────────────── -->
<?php if (empty($logs)): ?>
<p class="text-muted-2">No audit log entries found.</p>
<?php else: ?>
<p class="text-muted-2 text-sm mb-3">
    Showing <?= count($logs) ?> entr<?= count($logs) === 1 ? 'y' : 'ies' ?><?= count($logs) >= 100 ? ' (capped at 100)' : '' ?>.
</p>
<div class="scroll-x">
<table class="min-w-700">
    <thead>
        <tr>
            <th class="col-60">ID</th>
            <th class="col-140">Timestamp</th>
            <th>User</th>
            <th class="col-130">Entity Type</th>
            <th class="col-65">Entity ID</th>
            <th>Action</th>
            <th>Metadata Preview</th>
            <th class="col-50"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
    <?php
    $preview = '';
    if ($log['metadata_json'] !== null) {
        $compact = preg_replace('/\s+/', ' ', $log['metadata_json']);
        $preview = mb_strlen($compact) > 80 ? mb_substr($compact, 0, 77) . '…' : $compact;
    }
    ?>
    <tr>
        <td class="text-muted text-83"><?= (int) $log['id'] ?></td>
        <td class="text-82 text-muted nowrap"><?= View::e(substr($log['created_at'], 0, 16)) ?></td>
        <td class="text-sm">
            <?php if ($log['user_id']): ?>
                <a href="/admin/users/<?= (int) $log['user_id'] ?>"><?= View::e($log['user_email'] ?? '#' . $log['user_id']) ?></a>
            <?php else: ?>
                <span class="text-faint">system</span>
            <?php endif; ?>
        </td>
        <td class="text-83"><?= View::e($log['entity_type']) ?></td>
        <td class="text-83 text-muted"><?= (int) $log['entity_id'] ?></td>
        <td class="text-83 monospace"><?= View::e($log['action']) ?></td>
        <td class="text-xs text-muted monospace text-ellipsis mw-220">
            <?= View::e($preview) ?>
        </td>
        <td><a href="/admin/audit-logs/<?= (int) $log['id'] ?>" class="text-sm">View</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
