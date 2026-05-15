<div class="page-header">
    <h1>Plan Catalog</h1>
    <a href="/admin" class="back-link">&larr; Admin</a>
</div>

<div class="actions-group">
    <a href="/admin/plans/create" class="btn">New Plan</a>
</div>

<?php if (empty($plans)): ?>
    <p class="text-muted-2">No plans found.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th class="col-50">ID</th>
            <th>Internal Name</th>
            <th>Display Name</th>
            <th class="col-60 text-center">Public</th>
            <th class="col-60 text-center">Active</th>
            <th class="col-60 text-center">Legacy</th>
            <th class="col-60 text-right">Sort</th>
            <th class="col-70 text-right">Features</th>
            <th class="col-60"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($plans as $p): ?>
        <tr>
            <td><?= (int) $p['id'] ?></td>
            <td><code class="text-88"><?= View::e($p['internal_name']) ?></code></td>
            <td><?= View::e($p['display_name']) ?></td>
            <td class="text-center">
                <?= $p['is_public']  ? '<span class="text-success">&#10003;</span>' : '<span class="text-faint">&#8212;</span>' ?>
            </td>
            <td class="text-center">
                <?= $p['is_active']  ? '<span class="text-success">&#10003;</span>' : '<span class="text-danger">&#10007;</span>' ?>
            </td>
            <td class="text-center">
                <?= $p['is_legacy']  ? '<span class="text-amber text-82 fw-medium">legacy</span>' : '<span class="text-faint">&#8212;</span>' ?>
            </td>
            <td class="text-right text-muted text-88"><?= (int) $p['sort_order'] ?></td>
            <td class="text-right text-muted text-88"><?= (int) $p['feature_count'] ?></td>
            <td><a href="/admin/plans/<?= (int) $p['id'] ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
