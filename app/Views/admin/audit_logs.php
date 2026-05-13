<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1rem">
    <h1>Audit Logs</h1>
    <a href="/admin" style="color:#666;font-size:0.9rem">&larr; Admin</a>
</div>

<!-- ── Filters ─────────────────────────────────────────────────────────────── -->
<form method="get" action="/admin/audit-logs"
      style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:1rem;
             max-width:800px;margin-bottom:1.5rem">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;margin-bottom:0.75rem">
        <div>
            <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.2rem">Action</label>
            <input type="text" name="action" value="<?= View::e($action) ?>"
                   placeholder="e.g. plan_created"
                   style="width:100%;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.85rem">
        </div>
        <div>
            <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.2rem">Entity Type</label>
            <input type="text" name="entity_type" value="<?= View::e($entityType) ?>"
                   placeholder="e.g. plan"
                   style="width:100%;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.85rem">
        </div>
        <div>
            <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.2rem">User Email</label>
            <input type="text" name="user_email" value="<?= View::e($userEmail) ?>"
                   placeholder="partial match"
                   style="width:100%;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.85rem">
        </div>
        <div>
            <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.2rem">Date From</label>
            <input type="date" name="date_from" value="<?= View::e($dateFrom) ?>"
                   style="width:100%;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.85rem">
        </div>
        <div>
            <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.2rem">Date To</label>
            <input type="date" name="date_to" value="<?= View::e($dateTo) ?>"
                   style="width:100%;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.85rem">
        </div>
    </div>
    <div style="display:flex;gap:0.5rem">
        <button type="submit" class="btn" style="padding:0.3rem 0.9rem;font-size:0.85rem">Filter</button>
        <a href="/admin/audit-logs" class="btn btn-secondary" style="padding:0.3rem 0.9rem;font-size:0.85rem">Clear</a>
    </div>
</form>

<!-- ── Results ─────────────────────────────────────────────────────────────── -->
<?php if (empty($logs)): ?>
<p style="color:#888">No audit log entries found.</p>
<?php else: ?>
<p style="color:#888;font-size:0.85rem;margin-bottom:0.75rem">
    Showing <?= count($logs) ?> entr<?= count($logs) === 1 ? 'y' : 'ies' ?><?= count($logs) >= 100 ? ' (capped at 100)' : '' ?>.
</p>
<div style="overflow-x:auto">
<table style="min-width:700px">
    <thead>
        <tr>
            <th style="width:60px">ID</th>
            <th style="width:140px">Timestamp</th>
            <th>User</th>
            <th style="width:130px">Entity Type</th>
            <th style="width:65px">Entity ID</th>
            <th>Action</th>
            <th>Metadata Preview</th>
            <th style="width:50px"></th>
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
        <td style="color:#6b7280;font-size:0.83rem"><?= (int) $log['id'] ?></td>
        <td style="font-size:0.82rem;color:#6b7280;white-space:nowrap"><?= View::e(substr($log['created_at'], 0, 16)) ?></td>
        <td style="font-size:0.85rem">
            <?php if ($log['user_id']): ?>
                <a href="/admin/users/<?= (int) $log['user_id'] ?>"><?= View::e($log['user_email'] ?? '#' . $log['user_id']) ?></a>
            <?php else: ?>
                <span style="color:#9ca3af">system</span>
            <?php endif; ?>
        </td>
        <td style="font-size:0.83rem;color:#374151"><?= View::e($log['entity_type']) ?></td>
        <td style="font-size:0.83rem;color:#6b7280"><?= (int) $log['entity_id'] ?></td>
        <td style="font-size:0.83rem;font-family:monospace"><?= View::e($log['action']) ?></td>
        <td style="font-size:0.78rem;color:#6b7280;font-family:monospace;max-width:220px;
                   overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= View::e($preview) ?>
        </td>
        <td><a href="/admin/audit-logs/<?= (int) $log['id'] ?>" style="font-size:0.85rem">View</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
