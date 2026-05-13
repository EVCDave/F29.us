<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1rem">
    <h1>Subscription History</h1>
    <a href="/admin" style="color:#666;font-size:0.9rem">&larr; Admin</a>
</div>

<!-- ── Filters ─────────────────────────────────────────────────────────────── -->
<form method="get" action="/admin/subscriptions"
      style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:1rem;
             max-width:900px;margin-bottom:1.5rem">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;margin-bottom:0.75rem">
        <div>
            <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.2rem">User Email</label>
            <input type="text" name="user_email" value="<?= View::e($userEmail) ?>"
                   placeholder="partial match"
                   style="width:100%;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.85rem">
        </div>
        <div>
            <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.2rem">Plan</label>
            <select name="plan_id"
                    style="width:100%;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.85rem">
                <option value="0">All plans</option>
                <?php foreach ($plans as $p): ?>
                <option value="<?= (int) $p['id'] ?>"<?= $planId === (int) $p['id'] ? ' selected' : '' ?>>
                    <?= View::e($p['display_name']) ?> (<?= View::e($p['internal_name']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.2rem">Status</label>
            <select name="status"
                    style="width:100%;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.85rem">
                <option value="">Any</option>
                <?php foreach (['active', 'canceled', 'expired'] as $s): ?>
                <option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.2rem">Billing Cycle</label>
            <select name="billing_cycle"
                    style="width:100%;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.85rem">
                <option value="">Any</option>
                <?php foreach (['monthly', 'yearly', 'manual', 'free'] as $cy): ?>
                <option value="<?= $cy ?>"<?= $billingCycle === $cy ? ' selected' : '' ?>><?= $cy ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.2rem">Started From</label>
            <input type="date" name="date_from" value="<?= View::e($dateFrom) ?>"
                   style="width:100%;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.85rem">
        </div>
        <div>
            <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.2rem">Started To</label>
            <input type="date" name="date_to" value="<?= View::e($dateTo) ?>"
                   style="width:100%;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.85rem">
        </div>
    </div>
    <div style="display:flex;gap:0.5rem">
        <button type="submit" class="btn" style="padding:0.3rem 0.9rem;font-size:0.85rem">Filter</button>
        <a href="/admin/subscriptions" class="btn btn-secondary" style="padding:0.3rem 0.9rem;font-size:0.85rem">Clear</a>
    </div>
</form>

<!-- ── Results ─────────────────────────────────────────────────────────────── -->
<?php if (empty($subscriptions)): ?>
<p style="color:#888">No subscriptions found.</p>
<?php else: ?>
<p style="color:#888;font-size:0.85rem;margin-bottom:0.75rem">
    Showing <?= count($subscriptions) ?> subscription<?= count($subscriptions) === 1 ? '' : 's' ?><?= count($subscriptions) >= 100 ? ' (capped at 100)' : '' ?>.
</p>
<div style="overflow-x:auto">
<table style="min-width:820px">
    <thead>
        <tr>
            <th style="width:55px">ID</th>
            <th>User</th>
            <th>Plan</th>
            <th style="width:80px">Status</th>
            <th style="width:75px">Cycle</th>
            <th style="width:100px">Started</th>
            <th style="width:100px">Canceled</th>
            <th style="width:100px">Ends At</th>
            <th style="width:70px">GF'd</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($subscriptions as $s): ?>
    <?php
    $statusColors = [
        'active'   => 'color:#166534;font-weight:500',
        'canceled' => 'color:#6b7280',
        'expired'  => 'color:#991b1b',
    ];
    $statusStyle = $statusColors[$s['status']] ?? '';
    ?>
    <tr>
        <td style="color:#6b7280;font-size:0.83rem"><?= (int) $s['id'] ?></td>
        <td style="font-size:0.85rem">
            <a href="/admin/users/<?= (int) $s['user_id'] ?>"><?= View::e($s['user_email']) ?></a>
        </td>
        <td style="font-size:0.85rem">
            <a href="/admin/plans/<?= (int) $s['plan_id'] ?>"><?= View::e($s['plan_display_name']) ?></a>
            <span style="color:#9ca3af;font-size:0.78rem">(<?= View::e($s['plan_internal_name']) ?>)</span>
        </td>
        <td style="font-size:0.83rem;<?= $statusStyle ?>"><?= View::e($s['status']) ?></td>
        <td style="font-size:0.83rem;color:#6b7280"><?= View::e($s['billing_cycle']) ?></td>
        <td style="font-size:0.83rem;color:#6b7280;white-space:nowrap"><?= View::e(substr($s['started_at'], 0, 10)) ?></td>
        <td style="font-size:0.83rem;color:#6b7280;white-space:nowrap">
            <?= $s['canceled_at'] ? View::e(substr($s['canceled_at'], 0, 10)) : '<span style="color:#d1d5db">—</span>' ?>
        </td>
        <td style="font-size:0.83rem;color:#6b7280;white-space:nowrap">
            <?= $s['ends_at'] ? View::e(substr($s['ends_at'], 0, 10)) : '<span style="color:#d1d5db">—</span>' ?>
        </td>
        <td style="font-size:0.83rem;color:#6b7280;text-align:center">
            <?= $s['grandfathered_at'] ? '<span style="color:#166534">&#10003;</span>' : '<span style="color:#d1d5db">—</span>' ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
