<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1rem">
    <h1>Subscription Requests</h1>
    <a href="/admin" style="color:#666;font-size:0.9rem">&larr; Admin</a>
</div>

<?php if ($flash): ?>
<div class="notice" style="display:block;margin-bottom:1.25rem;
    <?= $flash['type'] === 'error'   ? 'background:#fef2f2;border-color:#fca5a5;color:#991b1b;' : '' ?>
    <?= $flash['type'] === 'success' ? 'background:#f0fdf4;border-color:#86efac;color:#166534;' : '' ?>">
    <?= View::e($flash['text']) ?>
</div>
<?php endif; ?>

<!-- ── Status filter ──────────────────────────────────────────────────────── -->
<div style="display:flex;gap:0.4rem;margin-bottom:1.5rem;flex-wrap:wrap">
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'canceled' => 'Canceled', 'all' => 'All'] as $s => $label): ?>
    <a href="/admin/subscription-requests?status=<?= $s ?>"
       style="font-size:0.82rem;padding:0.3rem 0.75rem;border-radius:4px;text-decoration:none;
              <?= $status === $s ? 'background:#1a1a2e;color:#fff' : 'background:#f3f4f6;color:#374151;border:1px solid #e5e7eb' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($requests)): ?>
<p style="color:#888">No <?= $status !== 'all' ? View::e($status) . ' ' : '' ?>requests found.</p>
<?php else: ?>
<p style="color:#888;font-size:0.85rem;margin-bottom:0.75rem">
    Showing <?= count($requests) ?> request(s)<?= count($requests) >= 200 ? ' (capped at 200)' : '' ?>.
</p>
<table>
    <thead>
        <tr>
            <th style="width:55px">ID</th>
            <th>User</th>
            <th>Current Plan</th>
            <th>Requested Plan</th>
            <th style="width:90px">Status</th>
            <th style="width:110px">Requested</th>
            <th style="width:110px">Reviewed</th>
            <th style="width:55px"></th>
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
                    <span style="color:#9ca3af">—</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="/admin/plans/<?= (int) $r['requested_plan_id'] ?>"><?= View::e($r['requested_plan_name']) ?></a>
                <span style="color:#9ca3af;font-size:0.8rem">(<?= View::e($r['requested_plan_internal']) ?>)</span>
            </td>
            <td><?php
                $colors = [
                    'pending'  => 'color:#92400e;font-weight:500',
                    'approved' => 'color:#166534;font-weight:500',
                    'denied'   => 'color:#991b1b;font-weight:500',
                    'canceled' => 'color:#6b7280',
                ];
                $style = $colors[$r['status']] ?? '';
                ?>
                <span style="<?= $style ?>"><?= View::e($r['status']) ?></span>
            </td>
            <td style="font-size:0.83rem;color:#6b7280"><?= View::e(substr($r['requested_at'], 0, 10)) ?></td>
            <td style="font-size:0.83rem;color:#6b7280">
                <?= $r['reviewed_at'] ? View::e(substr($r['reviewed_at'], 0, 10)) : '—' ?>
            </td>
            <td><a href="/admin/subscription-requests/<?= (int) $r['id'] ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
