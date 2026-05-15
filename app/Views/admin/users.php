<div class="page-header">
    <h1>Users</h1>
    <a href="/admin" class="back-link">&larr; Admin</a>
</div>

<form method="get" action="/admin/users" class="filter-form mb-6">
    <div class="form-group mb-0">
        <label for="q" class="filter-label">Search by email</label>
        <input type="text" id="q" name="q" value="<?= View::e($search) ?>" placeholder="user@example.com" class="mw-280">
    </div>
    <button type="submit" class="btn btn-secondary">Search</button>
    <?php if ($search !== ''): ?>
        <a href="/admin/users" class="filter-link">Clear</a>
    <?php endif; ?>
</form>

<?php if (empty($users)): ?>
    <p class="text-muted-2">No users found<?= $search !== '' ? ' matching "' . View::e($search) . '"' : '' ?>.</p>
<?php else: ?>
<p class="text-muted-2 text-sm mb-3">
    Showing <?= count($users) ?> user(s)<?= $search !== '' ? ' matching &ldquo;' . View::e($search) . '&rdquo;' : '' ?> (capped at 100)
</p>
<table>
    <thead>
        <tr>
            <th class="col-60">ID</th>
            <th>Email</th>
            <th class="col-70">Role</th>
            <th class="col-90">Status</th>
            <th>Plan</th>
            <th class="col-110">Created</th>
            <th class="col-60"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= (int) $u['id'] ?></td>
            <td><?= View::e($u['email']) ?></td>
            <td>
                <?php if ($u['role'] === 'admin'): ?>
                    <span class="text-blue fw-medium">admin</span>
                <?php else: ?>
                    <span class="text-muted">user</span>
                <?php endif; ?>
            </td>
            <td class="status-<?= View::e($u['status']) ?>"><?= View::e($u['status']) ?></td>
            <td><?= $u['plan_name'] !== null ? View::e($u['plan_name']) : '<span class="text-faint">—</span>' ?></td>
            <td class="text-sm text-muted"><?= View::e(substr($u['created_at'], 0, 10)) ?></td>
            <td><a href="/admin/users/<?= (int) $u['id'] ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
