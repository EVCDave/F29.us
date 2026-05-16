<div class="page-header">
    <h1><?= View::e($plan['display_name']) ?></h1>
    <a href="/admin/plans" class="back-link">&larr; Plans</a>
</div>

<?php if ($subActive > 0): ?>
<div class="card-warn mw-680 mb-5">
    <strong><?= $subActive ?> active subscription<?= $subActive !== 1 ? 's' : '' ?> currently use this plan.</strong>
    Editing features here affects all of them immediately.
    To change the offer for future users only, <a href="/admin/plans/<?= (int) $plan['id'] ?>/clone">clone this plan</a> into a new version first.
</div>
<?php endif; ?>

<!-- ── Plan info ──────────────────────────────────────────────────────────── -->
<table class="mw-560 mb-3">
    <tr>
        <th class="col-160">ID</th>
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
        <td><?= $plan['description'] !== null ? View::e($plan['description']) : '<span class="text-faint">—</span>' ?></td>
    </tr>
    <tr>
        <th>Monthly Price</th>
        <td><?= $plan['monthly_price_cents'] !== null ? (int) $plan['monthly_price_cents'] . ' cents' : '<span class="text-faint">—</span>' ?></td>
    </tr>
    <tr>
        <th>Yearly Price</th>
        <td><?= $plan['yearly_price_cents'] !== null ? (int) $plan['yearly_price_cents'] . ' cents' : '<span class="text-faint">—</span>' ?></td>
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
            if ($plan['is_public'])  $flags[] = '<span class="text-success fw-medium">public</span>';
            if ($plan['is_active'])  $flags[] = '<span class="text-blue fw-medium">active</span>';
            if ($plan['is_legacy'])  $flags[] = '<span class="text-amber fw-medium">legacy</span>';
            echo $flags ? implode(' &nbsp; ', $flags) : '<span class="text-faint">none</span>';
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
            <span class="text-faint">/</span>
            <?= $subTotal ?> total
        </td>
    </tr>
    <tr>
        <th>Created</th>
        <td class="text-sm"><?= View::e($plan['created_at']) ?></td>
    </tr>
</table>

<!-- ── Actions ────────────────────────────────────────────────────────────── -->
<div class="actions-group mb-2">
    <a href="/admin/plans/<?= (int) $plan['id'] ?>/edit" class="btn btn-secondary">Edit Metadata</a>
    <a href="/admin/plans/<?= (int) $plan['id'] ?>/clone" class="btn btn-secondary">Clone / New Version</a>

    <?php if (!$plan['is_legacy']): ?>
    <form method="post" action="/admin/plans/<?= (int) $plan['id'] ?>/retire" class="form-inline"
          data-confirm="Retire this plan? This sets it to non-public and legacy. Active subscribers are not affected.">
        <?= CsrfService::field() ?>
        <button type="submit" class="btn btn-secondary">Retire Plan</button>
    </form>
    <?php else: ?>
    <span class="btn-disabled" title="Already retired (is_legacy = 1)">Retire Plan</span>
    <?php endif; ?>
</div>

<p class="text-82 text-muted mb-10">
    <strong>Edit</strong> this plan to push changes to all current subscribers immediately. &nbsp;
    <strong>Clone</strong> to create a new version for future users without touching this one. &nbsp;
    <strong>Retire</strong> to remove it from public availability while keeping existing subscribers on it.
</p>

<!-- ── Plan features ──────────────────────────────────────────────────────── -->
<h2 class="mb-3">Features</h2>

<?php if (!empty($addErrors)): ?>
<ul class="errors mw-520">
    <?php foreach ($addErrors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if (!empty($features)): ?>
<div class="scroll-x mb-6">
<table class="min-w-700">
    <thead>
        <tr>
            <th>Feature Key</th>
            <th class="col-80">Type</th>
            <th class="col-130">Value</th>
            <th class="col-220"></th>
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
                <span class="badge text-muted ml-2">built-in</span>
                <?php endif; ?>
            </td>
            <?php if ($isEditing): ?>
            <td colspan="2">
                <?php if (!empty($updateErrors)): ?>
                <ul class="errors mb-2">
                    <?php foreach ($updateErrors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <form method="post"
                      action="/admin/plans/<?= (int) $plan['id'] ?>/features/<?= (int) $f['id'] ?>/update"
                      class="d-flex gap-2 align-start flex-wrap">
                    <?= CsrfService::field() ?>
                    <select name="value_type"
                            <?= $isBuiltin ? 'title="Built-in key — value type is fixed"' : '' ?>>
                        <?php foreach (['int', 'bool', 'string'] as $vt): ?>
                        <?php $expectedType = FeatureKeys::expectedType($f['feature_key']); ?>
                        <option value="<?= $vt ?>"
                                <?= $vt === $f['value_type'] ? 'selected' : '' ?>>
                            <?= $vt ?><?= ($isBuiltin && $expectedType === $vt) ? ' (required)' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="feature_value"
                           value="<?= View::e($f['feature_value']) ?>"
                           class="mw-140">
                    <button type="submit" class="btn btn-sm">Save</button>
                    <a href="/admin/plans/<?= (int) $plan['id'] ?>"
                       class="text-82 text-muted-3 align-self-center">Cancel</a>
                </form>
            </td>
            <?php else: ?>
            <td class="text-sm text-muted"><?= View::e($f['value_type']) ?></td>
            <td>
                <?php if ($f['value_type'] === 'bool'): ?>
                    <?= $f['feature_value'] === 'true'
                        ? '<span class="text-success fw-medium">true</span>'
                        : '<span class="text-danger fw-medium">false</span>' ?>
                <?php else: ?>
                    <?= View::e($f['feature_value']) ?>
                <?php endif; ?>
            </td>
            <?php endif; ?>
            <td class="nowrap">
                <?php if (!$isEditing): ?>
                <a href="/admin/plans/<?= (int) $plan['id'] ?>?edit_feature=<?= (int) $f['id'] ?>"
                   class="text-82 text-blue">Edit</a>
                &nbsp;
                <?php endif; ?>
                <form method="post"
                      action="/admin/plans/<?= (int) $plan['id'] ?>/features/<?= (int) $f['id'] ?>/delete"
                      class="form-inline"
                      data-confirm="Delete feature &quot;<?= View::e($f['feature_key']) ?>&quot;?">
                    <?= CsrfService::field() ?>
                    <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php else: ?>
<p class="text-muted-2 mb-5">No features defined yet.</p>
<?php endif; ?>

<!-- ── Add feature ────────────────────────────────────────────────────────── -->
<h3 class="mb-3">Add Feature</h3>

<form method="post" action="/admin/plans/<?= (int) $plan['id'] ?>/features" class="mw-520 mb-8">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label for="feature_key">Feature Key</label>
        <input type="text" id="feature_key" name="feature_key"
               value="<?= View::e($oldAdd['feature_key'] ?? '') ?>"
               placeholder="e.g. max_qr_codes" class="mw-280" autocomplete="off">
        <small class="hint">
            Lowercase letters, digits, underscores; must start with a letter. Fixed after creation.
            Built-in keys enforce their value type.
        </small>
    </div>

    <div class="form-group">
        <label for="value_type">Value Type</label>
        <select id="value_type" name="value_type" class="mw-140">
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
               class="mw-320">
    </div>

    <div class="form-group mt-4">
        <button type="submit" class="btn">Add Feature</button>
    </div>
</form>

<!-- ── Billing Price Mappings ──────────────────────────────────────────────── -->
<h2 class="mb-2">Billing Price Mappings</h2>
<p class="text-sm text-muted mb-4 mw-620">
    Maps this plan to a payment provider's price ID for future billing integration.
    No payments are processed — these are informational only and will be used when a billing
    provider (e.g. Stripe) is connected.
</p>

<?php
$isPaidPlan       = ($plan['monthly_price_cents'] > 0 || $plan['yearly_price_cents'] > 0);
$hasActiveMapping = !empty(array_filter($billingPrices, fn($bp) => (bool)$bp['is_active']));
if ($isPaidPlan && !$hasActiveMapping && $plan['is_active'] && !$plan['is_legacy']):
?>
<div class="card-warn mw-580 mb-4">
    This plan has a non-zero price but no active billing price mapping. Add a mapping below
    before enabling billing integration.
</div>
<?php endif; ?>

<?php if (!empty($billingPriceErrors)): ?>
<ul class="errors mw-520">
    <?php foreach ($billingPriceErrors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if (!empty($billingPrices)): ?>
<div class="scroll-x mb-6">
<table class="min-w-720">
    <thead>
        <tr>
            <th>Provider</th>
            <th class="col-70">Mode</th>
            <th>Price ID</th>
            <th class="col-90">Cycle</th>
            <th class="col-70">Currency</th>
            <th class="col-110">Amount</th>
            <th class="col-70">Active</th>
            <th class="col-110"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($billingPrices as $bp): ?>
        <tr>
            <td><?= View::e($bp['provider']) ?></td>
            <td class="text-sm">
                <?php $bpMode = $bp['provider_mode'] ?? 'test'; ?>
                <?php if ($bpMode === 'live'): ?>
                <span class="text-success fw-medium">live</span>
                <?php else: ?>
                <span class="text-muted">test</span>
                <?php endif; ?>
            </td>
            <td><code class="text-82"><?= View::e($bp['provider_price_id']) ?></code></td>
            <td class="text-sm text-muted"><?= View::e($bp['billing_cycle']) ?></td>
            <td class="text-sm text-muted"><?= View::e($bp['currency_code']) ?></td>
            <td class="text-sm">
                <?php if ($bp['amount_cents'] !== null): ?>
                    <?= View::e($bp['currency_code']) ?> <?= number_format((int) $bp['amount_cents'] / 100, 2) ?>
                <?php else: ?>
                    <span class="text-faint">—</span>
                <?php endif; ?>
            </td>
            <td>
                <?= $bp['is_active']
                    ? '<span class="text-success fw-medium">yes</span>'
                    : '<span class="text-faint">no</span>' ?>
            </td>
            <td>
                <form method="post"
                      action="/admin/plans/<?= (int) $plan['id'] ?>/billing-prices/<?= (int) $bp['id'] ?>/toggle"
                      class="form-inline">
                    <?= CsrfService::field() ?>
                    <button type="submit" class="btn btn-secondary btn-xs">
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
<p class="text-muted-2 mb-5">No billing price mappings defined.</p>
<?php endif; ?>

<!-- ── Add Billing Price ────────────────────────────────────────────────────── -->
<h3 class="mb-3">Add Price Mapping</h3>

<form method="post" action="/admin/plans/<?= (int) $plan['id'] ?>/billing-prices"
      class="mw-520 mb-8">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label for="bp_provider">Provider</label>
        <input type="text" id="bp_provider" name="provider"
               value="<?= View::e($oldBillingPrice['provider'] ?? 'stripe') ?>"
               placeholder="e.g. stripe" class="mw-180" autocomplete="off">
        <small class="hint">Lowercase letters, digits, underscores.</small>
    </div>

    <div class="form-group">
        <label for="bp_provider_mode">Provider Mode</label>
        <select id="bp_provider_mode" name="provider_mode" class="mw-120">
            <?php foreach (['test', 'live'] as $m): ?>
            <option value="<?= $m ?>"
                    <?= ($oldBillingPrice['provider_mode'] ?? $stripeMode) === $m ? 'selected' : '' ?>>
                <?= $m ?>
            </option>
            <?php endforeach; ?>
        </select>
        <small class="hint">Current app mode: <strong><?= View::e($stripeMode) ?></strong>. Stripe test and live Price IDs both start with <code>price_</code> — match this to where you copied the ID from.</small>
    </div>

    <div class="form-group">
        <label for="bp_price_id">Provider Price ID</label>
        <input type="text" id="bp_price_id" name="provider_price_id"
               value="<?= View::e($oldBillingPrice['provider_price_id'] ?? '') ?>"
               placeholder="e.g. price_1ABC2defGHIjklMN" class="mw-340" autocomplete="off">
        <small class="hint">Must start with <code>price_</code>. Product IDs (<code>prod_...</code>) are not valid.</small>
    </div>

    <div class="form-group">
        <label for="bp_billing_cycle">Billing Cycle</label>
        <select id="bp_billing_cycle" name="billing_cycle" class="mw-160">
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
               maxlength="3" placeholder="USD" class="mw-100">
    </div>

    <div class="form-group">
        <label for="bp_amount">Amount (cents, optional)</label>
        <input type="text" id="bp_amount" name="amount_cents"
               value="<?= View::e($oldBillingPrice['amount_cents'] ?? '') ?>"
               placeholder="e.g. 999 for $9.99" class="mw-180">
        <small class="hint">Leave blank if not yet determined.</small>
    </div>

    <div class="form-group mt-4">
        <button type="submit" class="btn">Add Price Mapping</button>
    </div>
</form>
