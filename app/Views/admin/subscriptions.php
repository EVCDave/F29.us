<div class="page-header">
    <h1>Subscription History</h1>
    <a href="/admin" class="back-link">&larr; Admin</a>
</div>

<!-- ── Filters ─────────────────────────────────────────────────────────────── -->
<form method="get" action="/admin/subscriptions" class="filter-panel mw-860">
    <div class="filter-panel-grid">
        <div>
            <label class="filter-label">User Email</label>
            <input type="text" name="user_email" value="<?= View::e($userEmail) ?>"
                   placeholder="partial match" class="filter-input">
        </div>
        <div>
            <label class="filter-label">Plan</label>
            <select name="plan_id" class="filter-input">
                <option value="0">All plans</option>
                <?php foreach ($plans as $p): ?>
                <option value="<?= (int) $p['id'] ?>"<?= $planId === (int) $p['id'] ? ' selected' : '' ?>>
                    <?= View::e($p['display_name']) ?> (<?= View::e($p['internal_name']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="filter-label">Status</label>
            <select name="status" class="filter-input">
                <option value="">Any</option>
                <?php foreach (['active', 'canceled', 'expired'] as $s): ?>
                <option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="filter-label">Billing Cycle</label>
            <select name="billing_cycle" class="filter-input">
                <option value="">Any</option>
                <?php foreach (['monthly', 'yearly', 'manual', 'free'] as $cy): ?>
                <option value="<?= $cy ?>"<?= $billingCycle === $cy ? ' selected' : '' ?>><?= $cy ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="filter-label">Started From</label>
            <input type="date" name="date_from" value="<?= View::e($dateFrom) ?>" class="filter-input">
        </div>
        <div>
            <label class="filter-label">Started To</label>
            <input type="date" name="date_to" value="<?= View::e($dateTo) ?>" class="filter-input">
        </div>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-sm">Filter</button>
        <a href="/admin/subscriptions" class="btn btn-secondary btn-sm">Clear</a>
    </div>
</form>

<!-- ── Results ─────────────────────────────────────────────────────────────── -->
<?php if (empty($subscriptions)): ?>
<p class="text-muted-2">No subscriptions found.</p>
<?php else: ?>
<p class="text-muted-2 text-sm mb-3">
    Showing <?= count($subscriptions) ?> subscription<?= count($subscriptions) === 1 ? '' : 's' ?><?= count($subscriptions) >= 100 ? ' (capped at 100)' : '' ?>.
</p>
<div class="scroll-x">
<table class="min-w-820">
    <thead>
        <tr>
            <th class="col-55">ID</th>
            <th>User</th>
            <th>Plan</th>
            <th class="col-80">Status</th>
            <th class="col-100">Billing State</th>
            <th class="col-75">Cycle</th>
            <th class="col-100">Started</th>
            <th class="col-100">Canceled</th>
            <th class="col-100">Ends At</th>
            <th class="col-70">GF'd</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($subscriptions as $s): ?>
    <tr>
        <td class="text-muted text-83"><?= (int) $s['id'] ?></td>
        <td class="text-sm">
            <a href="/admin/users/<?= (int) $s['user_id'] ?>"><?= View::e($s['user_email']) ?></a>
        </td>
        <td class="text-sm">
            <a href="/admin/plans/<?= (int) $s['plan_id'] ?>"><?= View::e($s['plan_display_name']) ?></a>
            <span class="text-faint text-xs">(<?= View::e($s['plan_internal_name']) ?>)</span>
        </td>
        <td class="text-83 status-<?= View::e($s['status']) ?>"><?= View::e($s['status']) ?></td>
        <td class="text-83 text-muted">
            <?= ($s['billing_status'] ?? 'not_applicable') !== 'not_applicable'
                ? View::e($s['billing_status'])
                : '<span class="text-dim">—</span>' ?>
        </td>
        <td class="text-83 text-muted"><?= View::e($s['billing_cycle']) ?></td>
        <td class="text-83 text-muted nowrap"><?= View::e(substr($s['started_at'], 0, 10)) ?></td>
        <td class="text-83 text-muted nowrap">
            <?= $s['canceled_at'] ? View::e(substr($s['canceled_at'], 0, 10)) : '<span class="text-dim">—</span>' ?>
        </td>
        <td class="text-83 text-muted nowrap">
            <?= $s['ends_at'] ? View::e(substr($s['ends_at'], 0, 10)) : '<span class="text-dim">—</span>' ?>
        </td>
        <td class="text-83 text-muted text-center">
            <?= $s['grandfathered_at'] ? '<span class="text-success">&#10003;</span>' : '<span class="text-dim">—</span>' ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
