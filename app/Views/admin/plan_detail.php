<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1.25rem">
    <h1><?= View::e($plan['display_name']) ?></h1>
    <a href="/admin/plans" style="color:#666;font-size:0.9rem">&larr; Plans</a>
</div>

<?php if ($subActive > 0): ?>
<div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:4px;padding:0.75rem 1rem;margin-bottom:1.25rem;max-width:640px">
    <strong><?= $subActive ?> active subscription<?= $subActive !== 1 ? 's' : '' ?> currently use this plan.</strong>
    Editing features here affects all of them immediately.
    To change the offer for future users only, <a href="/admin/plans/<?= (int) $plan['id'] ?>/clone">clone this plan</a> into a new version first.
</div>
<?php endif; ?>

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
        <td>
            <strong><?= $subActive ?></strong> active
            <span style="color:#9ca3af">/</span>
            <?= $subTotal ?> total
        </td>
    </tr>
    <tr>
        <th>Created</th>
        <td style="font-size:0.85rem"><?= View::e($plan['created_at']) ?></td>
    </tr>
</table>

<!-- ── Actions ────────────────────────────────────────────────────────────── -->
<div class="actions-group" style="margin-bottom:0.5rem">
    <a href="/admin/plans/<?= (int) $plan['id'] ?>/edit" class="btn btn-secondary">Edit Metadata</a>
    <a href="/admin/plans/<?= (int) $plan['id'] ?>/clone" class="btn btn-secondary">Clone / New Version</a>

    <?php if (!$plan['is_legacy']): ?>
    <form method="post" action="/admin/plans/<?= (int) $plan['id'] ?>/retire" style="display:inline"
          onsubmit="return confirm('Retire this plan? This sets it to non-public and legacy. Active subscribers are not affected.')">
        <?= CsrfService::field() ?>
        <button type="submit" class="btn btn-secondary">Retire Plan</button>
    </form>
    <?php else: ?>
    <span class="btn-disabled" title="Already retired (is_legacy = 1)">Retire Plan</span>
    <?php endif; ?>
</div>

<p style="font-size:0.82rem;color:#6b7280;margin-bottom:2.5rem">
    <strong>Edit</strong> this plan to push changes to all current subscribers immediately. &nbsp;
    <strong>Clone</strong> to create a new version for future users without touching this one. &nbsp;
    <strong>Retire</strong> to remove it from public availability while keeping existing subscribers on it.
</p>

<!-- ── Plan features ──────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.75rem">Features</h2>

<?php if (!empty($addErrors)): ?>
<ul class="errors" style="max-width:520px">
    <?php foreach ($addErrors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if (!empty($features)): ?>
<div style="overflow-x:auto;margin-bottom:1.5rem">
<table style="min-width:700px">
    <thead>
        <tr>
            <th>Feature Key</th>
            <th style="width:80px">Type</th>
            <th style="width:130px">Value</th>
            <th style="width:220px"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($features as $f): ?>
        <?php
        $isEditing = ($updateFeatureId === (int) $f['id']);
        $isBuiltin = FeatureKeys::isBuiltin($f['feature_key']);
        ?>
        <tr>
            <td>
                <code><?= View::e($f['feature_key']) ?></code>
                <?php if ($isBuiltin): ?>
                <span style="font-size:0.7rem;color:#6b7280;border:1px solid #d1d5db;padding:0 0.3rem;border-radius:3px;vertical-align:middle;margin-left:0.3rem">built-in</span>
                <?php endif; ?>
            </td>
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
                            style="padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.88rem;background:#fff"
                            <?= $isBuiltin ? 'title="Built-in key — value type is fixed"' : '' ?>>
                        <?php foreach (['int', 'bool', 'string'] as $vt): ?>
                        <?php $expectedType = FeatureKeys::expectedType($f['feature_key']); ?>
                        <option value="<?= $vt ?>"
                                <?= $vt === $f['value_type'] ? 'selected' : '' ?>
                                <?= ($isBuiltin && $expectedType !== null && $vt !== $expectedType) ? 'style="color:#9ca3af"' : '' ?>>
                            <?= $vt ?><?= ($isBuiltin && $expectedType === $vt) ? ' (required)' : '' ?>
                        </option>
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
            Built-in keys enforce their value type.
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

<!-- ── Billing Price Mappings ──────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.5rem">Billing Price Mappings</h2>
<p style="font-size:0.85rem;color:#6b7280;margin-bottom:1rem;max-width:620px">
    Maps this plan to a payment provider's price ID for future billing integration.
    No payments are processed — these are informational only and will be used when a billing
    provider (e.g. Stripe) is connected.
</p>

<?php
$isPaidPlan       = ($plan['monthly_price_cents'] > 0 || $plan['yearly_price_cents'] > 0);
$hasActiveMapping = !empty(array_filter($billingPrices, fn($bp) => (bool)$bp['is_active']));
if ($isPaidPlan && !$hasActiveMapping && $plan['is_active'] && !$plan['is_legacy']):
?>
<div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:4px;padding:0.65rem 0.9rem;
            max-width:580px;margin-bottom:1rem;font-size:0.88rem;color:#92400e">
    This plan has a non-zero price but no active billing price mapping. Add a mapping below
    before enabling billing integration.
</div>
<?php endif; ?>

<?php if (!empty($billingPriceErrors)): ?>
<ul class="errors" style="max-width:520px">
    <?php foreach ($billingPriceErrors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if (!empty($billingPrices)): ?>
<div style="overflow-x:auto;margin-bottom:1.5rem">
<table style="min-width:720px">
    <thead>
        <tr>
            <th>Provider</th>
            <th>Price ID</th>
            <th style="width:90px">Cycle</th>
            <th style="width:70px">Currency</th>
            <th style="width:110px">Amount</th>
            <th style="width:70px">Active</th>
            <th style="width:110px"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($billingPrices as $bp): ?>
        <tr>
            <td><?= View::e($bp['provider']) ?></td>
            <td><code style="font-size:0.82rem"><?= View::e($bp['provider_price_id']) ?></code></td>
            <td style="font-size:0.85rem;color:#6b7280"><?= View::e($bp['billing_cycle']) ?></td>
            <td style="font-size:0.85rem;color:#6b7280"><?= View::e($bp['currency_code']) ?></td>
            <td style="font-size:0.85rem">
                <?php if ($bp['amount_cents'] !== null): ?>
                    <?= View::e($bp['currency_code']) ?> <?= number_format((int) $bp['amount_cents'] / 100, 2) ?>
                <?php else: ?>
                    <span style="color:#9ca3af">—</span>
                <?php endif; ?>
            </td>
            <td>
                <?= $bp['is_active']
                    ? '<span style="color:#166534;font-weight:500">yes</span>'
                    : '<span style="color:#9ca3af">no</span>' ?>
            </td>
            <td>
                <form method="post"
                      action="/admin/plans/<?= (int) $plan['id'] ?>/billing-prices/<?= (int) $bp['id'] ?>/toggle"
                      style="display:inline">
                    <?= CsrfService::field() ?>
                    <button type="submit" class="btn btn-secondary"
                            style="padding:0.2rem 0.55rem;font-size:0.8rem">
                        <?= $bp['is_active'] ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php else: ?>
<p style="color:#888;margin-bottom:1.25rem">No billing price mappings defined.</p>
<?php endif; ?>

<!-- ── Add Billing Price ────────────────────────────────────────────────────── -->
<h3 style="font-size:1rem;font-weight:600;margin-bottom:0.75rem">Add Price Mapping</h3>

<form method="post" action="/admin/plans/<?= (int) $plan['id'] ?>/billing-prices"
      style="max-width:520px;margin-bottom:2rem">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label for="bp_provider">Provider</label>
        <input type="text" id="bp_provider" name="provider"
               value="<?= View::e($oldBillingPrice['provider'] ?? 'stripe') ?>"
               placeholder="e.g. stripe" style="max-width:180px" autocomplete="off">
        <small style="display:block;color:#888;margin-top:0.2rem">
            Lowercase letters, digits, underscores.
        </small>
    </div>

    <div class="form-group">
        <label for="bp_price_id">Provider Price ID</label>
        <input type="text" id="bp_price_id" name="provider_price_id"
               value="<?= View::e($oldBillingPrice['provider_price_id'] ?? '') ?>"
               placeholder="e.g. price_1ABC2defGHIjklMN" style="max-width:340px" autocomplete="off">
    </div>

    <div class="form-group">
        <label for="bp_billing_cycle">Billing Cycle</label>
        <select id="bp_billing_cycle" name="billing_cycle"
                style="display:block;max-width:160px;padding:0.45rem 0.65rem;border:1px solid #ccc;border-radius:4px;font-size:0.95rem;background:#fff">
            <?php foreach (['monthly', 'yearly'] as $cy): ?>
            <option value="<?= $cy ?>"
                    <?= ($oldBillingPrice['billing_cycle'] ?? 'monthly') === $cy ? 'selected' : '' ?>>
                <?= $cy ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="bp_currency">Currency Code</label>
        <input type="text" id="bp_currency" name="currency_code"
               value="<?= View::e($oldBillingPrice['currency_code'] ?? 'USD') ?>"
               maxlength="3" placeholder="USD" style="max-width:80px">
    </div>

    <div class="form-group">
        <label for="bp_amount">Amount (cents, optional)</label>
        <input type="text" id="bp_amount" name="amount_cents"
               value="<?= View::e($oldBillingPrice['amount_cents'] ?? '') ?>"
               placeholder="e.g. 999 for $9.99" style="max-width:180px">
        <small style="display:block;color:#888;margin-top:0.2rem">Leave blank if not yet determined.</small>
    </div>

    <div class="form-group" style="margin-top:1.1rem">
        <button type="submit" class="btn">Add Price Mapping</button>
    </div>
</form>
