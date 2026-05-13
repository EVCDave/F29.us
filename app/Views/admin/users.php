<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1rem">
    <h1>Users</h1>
    <a href="/admin" style="color:#666;font-size:0.9rem">&larr; Admin</a>
</div>

<form method="get" action="/admin/users" style="margin-bottom:1.5rem;display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap">
    <div class="form-group" style="margin:0">
        <label for="q" style="font-size:0.85rem">Search by email</label>
        <input type="text" id="q" name="q" value="<?= View::e($search) ?>" placeholder="user@example.com" style="max-width:280px">
    </div>
    <button type="submit" class="btn btn-secondary">Search</button>
    <?php if ($search !== ''): ?>
        <a href="/admin/users" style="font-size:0.85rem;color:#666;align-self:center">Clear</a>
    <?php endif; ?>
</form>

<?php if (empty($users)): ?>
    <p style="color:#888">No users found<?= $search !== '' ? ' matching "' . View::e($search) . '"' : '' ?>.</p>
<?php else: ?>
<p style="color:#888;font-size:0.85rem;margin-bottom:0.75rem">
    Showing <?= count($users) ?> user(s)<?= $search !== '' ? ' matching &ldquo;' . View::e($search) . '&rdquo;' : '' ?> (capped at 100)
</p>
<table>
    <thead>
        <tr>
            <th style="width:60px">ID</th>
            <th>Email</th>
            <th style="width:70px">Role</th>
            <th style="width:90px">Status</th>
            <th>Plan</th>
            <th style="width:110px">Created</th>
            <th style="width:60px"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= (int) $u['id'] ?></td>
            <td><?= View::e($u['email']) ?></td>
            <td>
                <?php if ($u['role'] === 'admin'): ?>
                    <span style="color:#1d4ed8;font-weight:500">admin</span>
                <?php else: ?>
                    <span style="color:#6b7280">user</span>
                <?php endif; ?>
            </td>
            <td class="status-<?= View::e($u['status']) ?>"><?= View::e($u['status']) ?></td>
            <td><?= $u['plan_name'] !== null ? View::e($u['plan_name']) : '<span style="color:#9ca3af">—</span>' ?></td>
            <td style="font-size:0.85rem;color:#6b7280"><?= View::e(substr($u['created_at'], 0, 10)) ?></td>
            <td><a href="/admin/users/<?= (int) $u['id'] ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
