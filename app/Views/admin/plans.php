<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1rem">
    <h1>Plan Catalog</h1>
    <a href="/admin" style="color:#666;font-size:0.9rem">&larr; Admin</a>
</div>

<div class="actions-group" style="margin-bottom:1.5rem">
    <a href="/admin/plans/create" class="btn">New Plan</a>
</div>

<?php if (empty($plans)): ?>
    <p style="color:#888">No plans found.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th style="width:50px">ID</th>
            <th>Internal Name</th>
            <th>Display Name</th>
            <th style="width:60px;text-align:center">Public</th>
            <th style="width:60px;text-align:center">Active</th>
            <th style="width:60px;text-align:center">Legacy</th>
            <th style="width:60px;text-align:right">Sort</th>
            <th style="width:70px;text-align:right">Features</th>
            <th style="width:60px"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($plans as $p): ?>
        <tr>
            <td><?= (int) $p['id'] ?></td>
            <td><code style="font-size:0.88rem"><?= View::e($p['internal_name']) ?></code></td>
            <td><?= View::e($p['display_name']) ?></td>
            <td style="text-align:center">
                <?= $p['is_public']  ? '<span style="color:#166534">&#10003;</span>' : '<span style="color:#9ca3af">&#8212;</span>' ?>
            </td>
            <td style="text-align:center">
                <?= $p['is_active']  ? '<span style="color:#166534">&#10003;</span>' : '<span style="color:#991b1b">&#10007;</span>' ?>
            </td>
            <td style="text-align:center">
                <?= $p['is_legacy']  ? '<span style="color:#d97706;font-size:0.8rem;font-weight:500">legacy</span>' : '<span style="color:#9ca3af">&#8212;</span>' ?>
            </td>
            <td style="text-align:right;color:#6b7280;font-size:0.88rem"><?= (int) $p['sort_order'] ?></td>
            <td style="text-align:right;color:#6b7280;font-size:0.88rem"><?= (int) $p['feature_count'] ?></td>
            <td><a href="/admin/plans/<?= (int) $p['id'] ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
