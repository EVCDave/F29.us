<div class="page-header">
    <h1>User #<?= (int) $user['id'] ?></h1>
    <a href="/admin/users" class="back-link">&larr; Users</a>
</div>

<!-- ── User info ─────────────────────────────────────────────────────────── -->
<table class="mw-560 mb-10">
    <tr>
        <th class="col-140">ID</th>
        <td><?= (int) $user['id'] ?></td>
    </tr>
    <tr>
        <th>Email</th>
        <td><?= View::e($user['email']) ?></td>
    </tr>
    <tr>
        <th>Role</th>
        <td>
            <?php if ($user['role'] === 'admin'): ?>
                <span class="text-blue fw-medium">admin</span>
            <?php else: ?>
                user
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th>Status</th>
        <td class="status-<?= View::e($user['status']) ?>"><?= View::e($user['status']) ?></td>
    </tr>
    <?php $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
    <?php if ($fullName !== ''): ?>
    <tr>
        <th>Name</th>
        <td><?= View::e($fullName) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!empty($user['display_name'])): ?>
    <tr>
        <th>Display name</th>
        <td><?= View::e($user['display_name']) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!empty($user['company_name'])): ?>
    <tr>
        <th>Company</th>
        <td><?= View::e($user['company_name']) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!empty($user['phone'])): ?>
    <tr>
        <th>Phone</th>
        <td><?= View::e($user['phone']) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!empty($user['timezone'])): ?>
    <tr>
        <th>Timezone</th>
        <td><?= View::e($user['timezone']) ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <th>Email verified</th>
        <td>
            <?php if (!empty($user['email_verified_at'])): ?>
                <span class="text-success">Yes</span>
                <span class="text-faint text-sm ml-2"><?= View::e(substr($user['email_verified_at'], 0, 16)) ?> UTC</span>
            <?php elseif ((int)($user['email_verification_required'] ?? 0) === 1): ?>
                <span class="text-danger">No (required)</span>
            <?php else: ?>
                <span class="text-muted">No (pre-existing)</span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th>Password changed</th>
        <td>
            <?php if (!empty($user['password_changed_at'])): ?>
                <?= View::e(substr($user['password_changed_at'], 0, 16)) ?> UTC
            <?php else: ?>
                <span class="text-faint">Not recorded</span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th>Last login</th>
        <td>
            <?php if (!empty($user['last_login_at'])): ?>
                <?= View::e(substr($user['last_login_at'], 0, 16)) ?> UTC
            <?php else: ?>
                <span class="text-faint">Never</span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th>Created</th>
        <td><?= View::e($user['created_at']) ?></td>
    </tr>
</table>

<!-- ── Subscription history ──────────────────────────────────────────────── -->
<h2 class="mb-3">Subscriptions</h2>

<?php if (empty($subscriptions)): ?>
<p class="text-muted-2 mb-8">No subscriptions found.</p>
<?php else: ?>
<div class="scroll-x mb-2">
<table class="min-w-700">
    <thead>
        <tr>
            <th>Plan</th>
            <th>Status</th>
            <th class="col-75">Cycle</th>
            <th class="col-100">Billing State</th>
            <th class="col-185">Provider Sub ID</th>
            <th class="col-100">Period End</th>
            <th class="col-75">Cancel EOP</th>
            <th class="col-85">Started</th>
            <th class="col-85">Canceled</th>
            <th class="col-85">GF'd</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($subscriptions as $sub): ?>
        <tr>
            <td>
                <?= View::e($sub['display_name']) ?>
                <small class="text-muted-2">(<?= View::e($sub['internal_name']) ?>)</small>
            </td>
            <td class="status-<?= View::e($sub['status']) ?>"><?= View::e($sub['status']) ?></td>
            <td class="text-83 text-muted"><?= View::e($sub['billing_cycle']) ?></td>
            <td class="text-83 text-muted">
                <?= ($sub['billing_status'] ?? 'not_applicable') !== 'not_applicable'
                    ? View::e($sub['billing_status'])
                    : '<span class="text-dim">—</span>' ?>
            </td>
            <td class="text-xs text-muted word-break mw-180">
                <?= $sub['provider_subscription_id']
                    ? View::e($sub['provider_subscription_id'])
                    : '<span class="text-dim">—</span>' ?>
            </td>
            <td class="text-83 text-muted nowrap">
                <?= $sub['current_period_end']
                    ? View::e(substr($sub['current_period_end'], 0, 10))
                    : '<span class="text-dim">—</span>' ?>
            </td>
            <td class="text-83 text-muted text-center">
                <?= $sub['cancel_at_period_end']
                    ? '<span class="text-amber">yes</span>'
                    : '<span class="text-dim">—</span>' ?>
            </td>
            <td class="text-83 text-muted"><?= View::e(substr($sub['started_at'], 0, 10)) ?></td>
            <td class="text-83 text-muted"><?= $sub['canceled_at'] ? View::e(substr($sub['canceled_at'], 0, 10)) : '<span class="text-dim">—</span>' ?></td>
            <td class="text-83 text-muted"><?= $sub['grandfathered_at'] ? View::e(substr($sub['grandfathered_at'], 0, 10)) : '<span class="text-dim">—</span>' ?></td>
            <td class="text-82 text-muted word-break-word mw-180"><?= $sub['notes'] ? View::e($sub['notes']) : '' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="text-xs text-dim mb-10">Showing up to 10 most recent.</p>
<?php endif; ?>

<!-- ── Change subscription ────────────────────────────────────────────────── -->
<h2 class="mb-3">Change Subscription</h2>

<?php if (!empty($errors)): ?>
<ul class="errors mw-520">
    <?php foreach ($errors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/admin/users/<?= (int) $user['id'] ?>/subscription" class="mw-520 mb-12">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label for="plan_id">Plan</label>
        <select id="plan_id" name="plan_id" class="mw-320 w-full">
            <option value="">— select plan —</option>
            <?php foreach ($plans as $plan): ?>
            <option value="<?= (int) $plan['id'] ?>"
                <?= ((int) ($oldSub['plan_id'] ?? 0)) === (int) $plan['id'] ? 'selected' : '' ?>>
                <?= View::e($plan['display_name']) ?> (<?= View::e($plan['internal_name']) ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="billing_cycle">Billing Cycle</label>
        <select id="billing_cycle" name="billing_cycle" class="mw-180">
            <?php foreach (['free', 'manual', 'monthly', 'yearly'] as $cycle): ?>
            <option value="<?= $cycle ?>" <?= ($oldSub['billing_cycle'] ?? 'manual') === $cycle ? 'selected' : '' ?>>
                <?= $cycle ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group form-check">
        <input type="checkbox" id="grandfathered" name="grandfathered" value="1"
               <?= !empty($oldSub['grandfathered']) ? 'checked' : '' ?>>
        <label for="grandfathered" class="fw-normal">Mark as grandfathered (sets <code>grandfathered_at</code> to now)</label>
    </div>

    <div class="form-group">
        <label for="sub_notes">Notes (optional)</label>
        <textarea id="sub_notes" name="notes" rows="2" maxlength="1000"
        ><?= View::e($oldSub['notes'] ?? '') ?></textarea>
    </div>

    <div class="form-group mt-4">
        <button type="submit" class="btn">Assign Plan</button>
    </div>
</form>

<!-- ── Effective entitlements ─────────────────────────────────────────────── -->
<h2 class="mb-3">Effective Entitlements</h2>

<?php if (empty($entitlements)): ?>
<p class="text-muted-2 mb-10">No entitlements resolved — user may have no active subscription.</p>
<?php else: ?>
<table class="mw-560 mb-10">
    <thead>
        <tr>
            <th>Feature Key</th>
            <th>Value</th>
            <th class="col-90">Source</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($entitlements as $key => $value): ?>
        <tr>
            <td><code><?= View::e($key) ?></code></td>
            <td>
                <?php if (is_bool($value)): ?>
                    <?= $value
                        ? '<span class="text-success fw-medium">true</span>'
                        : '<span class="text-danger fw-medium">false</span>' ?>
                <?php else: ?>
                    <?= View::e((string) $value) ?>
                <?php endif; ?>
            </td>
            <td class="text-sm">
                <?php if (in_array($key, $overriddenKeys, true)): ?>
                    <span class="text-amber fw-medium">override</span>
                <?php else: ?>
                    <span class="text-muted">plan</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- ── Feature overrides ──────────────────────────────────────────────────── -->
<h2 class="mb-3">Feature Overrides</h2>

<?php if (!empty($overrideErrors)): ?>
<ul class="errors mw-520">
    <?php foreach ($overrideErrors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if (!empty($overrides)): ?>
<div class="scroll-x mb-5">
<table class="min-w-680">
    <thead>
        <tr>
            <th>Feature Key</th>
            <th class="col-70">Type</th>
            <th>Value</th>
            <th class="col-150">Expires</th>
            <th>Note</th>
            <th class="col-80"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($overrides as $ov): ?>
        <tr>
            <td><code><?= View::e($ov['feature_key']) ?></code></td>
            <td><?= View::e($ov['value_type']) ?></td>
            <td><?= View::e($ov['feature_value']) ?></td>
            <td class="text-sm"><?= $ov['expires_at'] ? View::e(substr($ov['expires_at'], 0, 16)) : '—' ?></td>
            <td class="text-sm text-muted"><?= $ov['note'] ? View::e($ov['note']) : '' ?></td>
            <td>
                <form method="post"
                      action="/admin/users/<?= (int) $user['id'] ?>/overrides/<?= (int) $ov['id'] ?>/delete"
                      class="form-inline"
                      data-confirm="Delete override for <?= View::e($ov['feature_key']) ?>?">
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
<p class="text-muted-2 mb-4">No feature overrides set.</p>
<?php endif; ?>

<h3 class="mb-3">Add / Update Override</h3>
<p class="text-sm text-muted mb-4">
    If a key already exists for this user it will be replaced.
</p>

<form method="post" action="/admin/users/<?= (int) $user['id'] ?>/overrides" class="mw-520 mb-8">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label for="feature_key">Feature Key</label>
        <input type="text" id="feature_key" name="feature_key"
               value="<?= View::e($oldOverride['feature_key'] ?? '') ?>"
               placeholder="e.g. max_qr_codes" class="mw-280">
        <small class="hint">Lowercase letters, digits, underscores; must start with a letter.</small>
    </div>

    <div class="form-group">
        <label for="value_type">Value Type</label>
        <select id="value_type" name="value_type" class="mw-140">
            <?php foreach (['int', 'bool', 'string'] as $vt): ?>
            <option value="<?= $vt ?>" <?= ($oldOverride['value_type'] ?? 'int') === $vt ? 'selected' : '' ?>>
                <?= $vt ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="feature_value">Value</label>
        <input type="text" id="feature_value" name="feature_value"
               value="<?= View::e($oldOverride['feature_value'] ?? '') ?>"
               placeholder="int: 100 &nbsp;|&nbsp; bool: true or false &nbsp;|&nbsp; string: any"
               class="mw-320">
    </div>

    <div class="form-group">
        <label for="expires_at">Expires At (optional)</label>
        <input type="datetime-local" id="expires_at" name="expires_at"
               value="<?= View::e($oldOverride['expires_at'] ?? '') ?>"
               class="mw-220">
        <small class="hint">Leave blank for no expiry.</small>
    </div>

    <div class="form-group">
        <label for="override_note">Note (optional)</label>
        <input type="text" id="override_note" name="note" maxlength="255"
               value="<?= View::e($oldOverride['note'] ?? '') ?>"
               class="mw-380" placeholder="Reason for override…">
    </div>

    <div class="form-group mt-4">
        <button type="submit" class="btn">Save Override</button>
    </div>
</form>
