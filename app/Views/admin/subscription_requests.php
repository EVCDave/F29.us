<div class="page-header">
    <h1>Subscription Requests</h1>
    <a href="/admin" class="back-link">&larr; Admin</a>
</div>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<!-- ── Status filter ──────────────────────────────────────────────────────── -->
<div class="d-flex gap-2 mb-6 flex-wrap">
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'canceled' => 'Canceled', 'all' => 'All'] as $s => $label): ?>
    <a href="/admin/subscription-requests?status=<?= $s ?>"
       class="tab-link <?= $status === $s ? 'tab-link-active' : '' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($requests)): ?>
<p class="text-muted-2">No <?= $status !== 'all' ? View::e($status) . ' ' : '' ?>requests found.</p>
<?php else: ?>
<p class="text-muted-2 text-sm mb-3">
    Showing <?= count($requests) ?> request(s)<?= count($requests) >= 200 ? ' (capped at 200)' : '' ?>.
</p>
<table>
    <thead>
        <tr>
            <th class="col-55">ID</th>
            <th>User</th>
            <th>Current Plan</th>
            <th>Requested Plan</th>
            <th class="col-90">Status</th>
            <th class="col-110">Requested</th>
            <th class="col-110">Reviewed</th>
            <th class="col-55"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($requests as $r): ?>
        <tr>
            <td><?= (int) $r['id'] ?></td>
            <td>
                <a href="/admin/users/<?= (int) $r['user_id'] ?>"><?= View::e($r['user_email']) ?></a>
            </td>
            <td>
                <?php if ($r['current_plan_name'] !== null && $r['current_plan_id']): ?>
                    <a href="/admin/plans/<?= (int) $r['current_plan_id'] ?>"><?= View::e($r['current_plan_name']) ?></a>
                <?php elseif ($r['current_plan_name'] !== null): ?>
                    <?= View::e($r['current_plan_name']) ?>
                <?php else: ?>
                    <span class="text-faint">—</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="/admin/plans/<?= (int) $r['requested_plan_id'] ?>"><?= View::e($r['requested_plan_name']) ?></a>
                <span class="text-faint text-82">(<?= View::e($r['requested_plan_internal']) ?>)</span>
            </td>
            <td class="status-<?= View::e($r['status']) ?>"><?= View::e($r['status']) ?></td>
            <td class="text-83 text-muted"><?= View::e(substr($r['requested_at'], 0, 10)) ?></td>
            <td class="text-83 text-muted">
                <?= $r['reviewed_at'] ? View::e(substr($r['reviewed_at'], 0, 10)) : '—' ?>
            </td>
            <td><a href="/admin/subscription-requests/<?= (int) $r['id'] ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
