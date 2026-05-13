<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1.25rem">
    <h1><?= View::e($plan['display_name']) ?></h1>
    <a href="/admin/plans" style="color:#666;font-size:0.9rem">&larr; Plans</a>
</div>

<!-- ── Plan info ──────────────────────────────────────────────────────────── -->
<table style="max-width:560px;margin-bottom:0.75rem">
    <tr>
        <th style="width:160px">ID</th>
        <td><?= (int) $plan['id'] ?></td>
    </tr>
    <tr>
        <th>Internal Name</th>
        <td><code><?= View::e($plan['internal_name']) ?></code></td>
    </tr>
    <tr>
        <th>Display Name</th>
        <td><?= View::e($plan['display_name']) ?></td>
    </tr>
    <tr>
        <th>Description</th>
        <td><?= $plan['description'] !== null ? View::e($plan['description']) : '<span style="color:#9ca3af">—</span>' ?></td>
    </tr>
    <tr>
        <th>Monthly Price</th>
        <td><?= $plan['monthly_price_cents'] !== null ? (int) $plan['monthly_price_cents'] . ' cents' : '<span style="color:#9ca3af">—</span>' ?></td>
    </tr>
    <tr>
        <th>Yearly Price</th>
        <td><?= $plan['yearly_price_cents'] !== null ? (int) $plan['yearly_price_cents'] . ' cents' : '<span style="color:#9ca3af">—</span>' ?></td>
    </tr>
    <tr>
        <th>Currency</th>
        <td><?= View::e($plan['currency_code']) ?></td>
    </tr>
    <tr>
        <th>Flags</th>
        <td>
            <?php
            $flags = [];
            if ($plan['is_public'])  $flags[] = '<span style="color:#166534;font-weight:500">public</span>';
            if ($plan['is_active'])  $flags[] = '<span style="color:#1d4ed8;font-weight:500">active</span>';
            if ($plan['is_legacy'])  $flags[] = '<span style="color:#d97706;font-weight:500">legacy</span>';
            echo $flags ? implode(' &nbsp; ', $flags) : '<span style="color:#9ca3af">none</span>';
            ?>
        </td>
    </tr>
    <tr>
        <th>Sort Order</th>
        <td><?= (int) $plan['sort_order'] ?></td>
    </tr>
    <tr>
        <th>Subscriptions</th>
        <td><?= (int) $subActive ?> active / <?= (int) $subTotal ?> total</td>
    </tr>
    <tr>
        <th>Created</th>
        <td style="font-size:0.85rem"><?= View::e($plan['created_at']) ?></td>
    </tr>
</table>

<div class="actions-group" style="margin-bottom:2.5rem">
    <a href="/admin/plans/<?= (int) $plan['id'] ?>/edit" class="btn btn-secondary">Edit Metadata</a>
</div>

<!-- ── Plan features ──────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.75rem">Features</h2>

<?php if (!empty($addErrors)): ?>
<ul class="errors" style="max-width:520px">
    <?php foreach ($addErrors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if (!empty($features)): ?>
<div style="overflow-x:auto;margin-bottom:1.5rem">
<table style="min-width:680px">
    <thead>
        <tr>
            <th>Feature Key</th>
            <th style="width:80px">Type</th>
            <th style="width:120px">Value</th>
            <th style="width:200px"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($features as $f): ?>
        <?php $isEditing = ($updateFeatureId === (int) $f['id']); ?>
        <tr>
            <td><code><?= View::e($f['feature_key']) ?></code></td>
            <?php if ($isEditing): ?>
            <td colspan="2" style="padding:0.4rem 0.75rem">
                <?php if (!empty($updateErrors)): ?>
                <ul class="errors" style="margin-bottom:0.5rem">
                    <?php foreach ($updateErrors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <form method="post"
                      action="/admin/plans/<?= (int) $plan['id'] ?>/features/<?= (int) $f['id'] ?>/update"
                      style="display:flex;gap:0.5rem;align-items:flex-start;flex-wrap:wrap">
                    <?= CsrfService::field() ?>
                    <select name="value_type"
                            style="padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.88rem;background:#fff">
                        <?php foreach (['int', 'bool', 'string'] as $vt): ?>
                        <option value="<?= $vt ?>" <?= $vt === $f['value_type'] ? 'selected' : '' ?>><?= $vt ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="feature_value"
                           value="<?= View::e($f['feature_value']) ?>"
                           style="padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.88rem;max-width:140px">
                    <button type="submit" class="btn" style="padding:0.3rem 0.75rem;font-size:0.82rem">Save</button>
                    <a href="/admin/plans/<?= (int) $plan['id'] ?>"
                       style="font-size:0.82rem;color:#666;align-self:center">Cancel</a>
                </form>
            </td>
            <?php else: ?>
            <td style="font-size:0.85rem;color:#6b7280"><?= View::e($f['value_type']) ?></td>
            <td>
                <?php if ($f['value_type'] === 'bool'): ?>
                    <?= $f['feature_value'] === 'true'
                        ? '<span style="color:#166534;font-weight:500">true</span>'
                        : '<span style="color:#991b1b;font-weight:500">false</span>' ?>
                <?php else: ?>
                    <?= View::e($f['feature_value']) ?>
                <?php endif; ?>
            </td>
            <?php endif; ?>
            <td style="white-space:nowrap">
                <?php if (!$isEditing): ?>
                <a href="/admin/plans/<?= (int) $plan['id'] ?>?edit_feature=<?= (int) $f['id'] ?>"
                   style="font-size:0.82rem;color:#0066cc">Edit</a>
                &nbsp;
                <?php endif; ?>
                <form method="post"
                      action="/admin/plans/<?= (int) $plan['id'] ?>/features/<?= (int) $f['id'] ?>/delete"
                      style="display:inline"
                      onsubmit="return confirm('Delete feature &quot;<?= View::e($f['feature_key']) ?>&quot;?')">
                    <?= CsrfService::field() ?>
                    <button type="submit" class="btn btn-danger"
                            style="padding:0.2rem 0.55rem;font-size:0.8rem">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php else: ?>
<p style="color:#888;margin-bottom:1.25rem">No features defined yet.</p>
<?php endif; ?>

<!-- ── Add feature ────────────────────────────────────────────────────────── -->
<h3 style="font-size:1rem;font-weight:600;margin-bottom:0.75rem">Add Feature</h3>

<form method="post" action="/admin/plans/<?= (int) $plan['id'] ?>/features" style="max-width:520px;margin-bottom:2rem">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label for="feature_key">Feature Key</label>
        <input type="text" id="feature_key" name="feature_key"
               value="<?= View::e($oldAdd['feature_key'] ?? '') ?>"
               placeholder="e.g. max_qr_codes" style="max-width:280px" autocomplete="off">
        <small style="display:block;color:#888;margin-top:0.2rem">
            Lowercase letters, digits, underscores; must start with a letter. Fixed after creation.
        </small>
    </div>

    <div class="form-group">
        <label for="value_type">Value Type</label>
        <select id="value_type" name="value_type"
                style="display:block;max-width:140px;padding:0.45rem 0.65rem;border:1px solid #ccc;border-radius:4px;font-size:0.95rem;background:#fff">
            <?php foreach (['int', 'bool', 'string'] as $vt): ?>
            <option value="<?= $vt ?>" <?= ($oldAdd['value_type'] ?? 'int') === $vt ? 'selected' : '' ?>>
                <?= $vt ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="feature_value">Value</label>
        <input type="text" id="feature_value" name="feature_value"
               value="<?= View::e($oldAdd['feature_value'] ?? '') ?>"
               placeholder="int: 100 &nbsp;|&nbsp; bool: true or false &nbsp;|&nbsp; string: any"
               style="max-width:320px">
    </div>

    <div class="form-group" style="margin-top:1.1rem">
        <button type="submit" class="btn">Add Feature</button>
    </div>
</form>
