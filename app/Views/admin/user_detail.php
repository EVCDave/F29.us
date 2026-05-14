<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1.25rem">
    <h1>User #<?= (int) $user['id'] ?></h1>
    <a href="/admin/users" style="color:#666;font-size:0.9rem">&larr; Users</a>
</div>

<!-- ── User info ─────────────────────────────────────────────────────────── -->
<table style="max-width:560px;margin-bottom:2.5rem">
    <tr>
        <th style="width:140px">ID</th>
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
                <span style="color:#1d4ed8;font-weight:500">admin</span>
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
                <span style="color:#166534">Yes</span>
                <span style="color:#9ca3af;font-size:0.85rem;margin-left:0.4rem"><?= View::e(substr($user['email_verified_at'], 0, 16)) ?> UTC</span>
            <?php elseif ((int)($user['email_verification_required'] ?? 0) === 1): ?>
                <span style="color:#991b1b">No (required)</span>
            <?php else: ?>
                <span style="color:#6b7280">No (pre-existing)</span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th>Created</th>
        <td><?= View::e($user['created_at']) ?></td>
    </tr>
</table>

<!-- ── Subscription history ──────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.75rem">Subscriptions</h2>

<?php if (empty($subscriptions)): ?>
<p style="color:#888;margin-bottom:2rem">No subscriptions found.</p>
<?php else: ?>
<div style="overflow-x:auto;margin-bottom:0.5rem">
<table style="min-width:700px">
    <thead>
        <tr>
            <th>Plan</th>
            <th>Status</th>
            <th style="width:75px">Cycle</th>
            <th style="width:100px">Billing State</th>
            <th style="width:185px">Provider Sub ID</th>
            <th style="width:100px">Period End</th>
            <th style="width:75px">Cancel EOP</th>
            <th style="width:85px">Started</th>
            <th style="width:85px">Canceled</th>
            <th style="width:85px">GF'd</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($subscriptions as $sub): ?>
        <tr>
            <td>
                <?= View::e($sub['display_name']) ?>
                <small style="color:#888">(<?= View::e($sub['internal_name']) ?>)</small>
            </td>
            <td class="status-<?= View::e($sub['status']) ?>"><?= View::e($sub['status']) ?></td>
            <td style="font-size:0.83rem;color:#6b7280"><?= View::e($sub['billing_cycle']) ?></td>
            <td style="font-size:0.83rem;color:#6b7280">
                <?= ($sub['billing_status'] ?? 'not_applicable') !== 'not_applicable'
                    ? View::e($sub['billing_status'])
                    : '<span style="color:#d1d5db">—</span>' ?>
            </td>
            <td style="font-size:0.78rem;color:#6b7280;word-break:break-all;max-width:180px">
                <?= $sub['provider_subscription_id']
                    ? View::e($sub['provider_subscription_id'])
                    : '<span style="color:#d1d5db">—</span>' ?>
            </td>
            <td style="font-size:0.83rem;color:#6b7280;white-space:nowrap">
                <?= $sub['current_period_end']
                    ? View::e(substr($sub['current_period_end'], 0, 10))
                    : '<span style="color:#d1d5db">—</span>' ?>
            </td>
            <td style="font-size:0.83rem;color:#6b7280;text-align:center">
                <?= $sub['cancel_at_period_end']
                    ? '<span style="color:#d97706">yes</span>'
                    : '<span style="color:#d1d5db">—</span>' ?>
            </td>
            <td style="font-size:0.83rem;color:#6b7280"><?= View::e(substr($sub['started_at'], 0, 10)) ?></td>
            <td style="font-size:0.83rem;color:#6b7280"><?= $sub['canceled_at'] ? View::e(substr($sub['canceled_at'], 0, 10)) : '<span style="color:#d1d5db">—</span>' ?></td>
            <td style="font-size:0.83rem;color:#6b7280"><?= $sub['grandfathered_at'] ? View::e(substr($sub['grandfathered_at'], 0, 10)) : '<span style="color:#d1d5db">—</span>' ?></td>
            <td style="font-size:0.82rem;color:#6b7280;max-width:180px;word-break:break-word"><?= $sub['notes'] ? View::e($sub['notes']) : '' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p style="font-size:0.8rem;color:#9ca3af;margin-bottom:2.5rem">Showing up to 10 most recent.</p>
<?php endif; ?>

<!-- ── Change subscription ────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.75rem">Change Subscription</h2>

<?php if (!empty($errors)): ?>
<ul class="errors" style="max-width:520px">
    <?php foreach ($errors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/admin/users/<?= (int) $user['id'] ?>/subscription" style="max-width:520px;margin-bottom:3rem">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label for="plan_id">Plan</label>
        <select id="plan_id" name="plan_id" style="display:block;width:100%;max-width:320px;padding:0.45rem 0.65rem;border:1px solid #ccc;border-radius:4px;font-size:0.95rem;background:#fff">
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
        <select id="billing_cycle" name="billing_cycle" style="display:block;max-width:180px;padding:0.45rem 0.65rem;border:1px solid #ccc;border-radius:4px;font-size:0.95rem;background:#fff">
            <?php foreach (['free', 'manual', 'monthly', 'yearly'] as $cycle): ?>
            <option value="<?= $cycle ?>" <?= ($oldSub['billing_cycle'] ?? 'manual') === $cycle ? 'selected' : '' ?>>
                <?= $cycle ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" style="display:flex;align-items:center;gap:0.5rem">
        <input type="checkbox" id="grandfathered" name="grandfathered" value="1"
               <?= !empty($oldSub['grandfathered']) ? 'checked' : '' ?>>
        <label for="grandfathered" style="margin:0;font-weight:400">Mark as grandfathered (sets <code>grandfathered_at</code> to now)</label>
    </div>

    <div class="form-group">
        <label for="sub_notes">Notes (optional)</label>
        <textarea id="sub_notes" name="notes" rows="2" maxlength="1000"
            style="display:block;width:100%;max-width:520px;padding:0.45rem 0.65rem;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;resize:vertical"
        ><?= View::e($oldSub['notes'] ?? '') ?></textarea>
    </div>

    <div class="form-group" style="margin-top:1.1rem">
        <button type="submit" class="btn">Assign Plan</button>
    </div>
</form>

<!-- ── Effective entitlements ─────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.75rem">Effective Entitlements</h2>

<?php if (empty($entitlements)): ?>
<p style="color:#888;margin-bottom:2.5rem">No entitlements resolved — user may have no active subscription.</p>
<?php else: ?>
<table style="max-width:560px;margin-bottom:2.5rem">
    <thead>
        <tr>
            <th>Feature Key</th>
            <th>Value</th>
            <th style="width:90px">Source</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($entitlements as $key => $value): ?>
        <tr>
            <td><code><?= View::e($key) ?></code></td>
            <td>
                <?php if (is_bool($value)): ?>
                    <?= $value
                        ? '<span style="color:#166534;font-weight:500">true</span>'
                        : '<span style="color:#991b1b;font-weight:500">false</span>' ?>
                <?php else: ?>
                    <?= View::e((string) $value) ?>
                <?php endif; ?>
            </td>
            <td style="font-size:0.85rem">
                <?php if (in_array($key, $overriddenKeys, true)): ?>
                    <span style="color:#d97706;font-weight:500">override</span>
                <?php else: ?>
                    <span style="color:#6b7280">plan</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- ── Feature overrides ──────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.75rem">Feature Overrides</h2>

<?php if (!empty($overrideErrors)): ?>
<ul class="errors" style="max-width:520px">
    <?php foreach ($overrideErrors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if (!empty($overrides)): ?>
<div style="overflow-x:auto;margin-bottom:1.25rem">
<table style="min-width:680px">
    <thead>
        <tr>
            <th>Feature Key</th>
            <th style="width:70px">Type</th>
            <th>Value</th>
            <th style="width:150px">Expires</th>
            <th>Note</th>
            <th style="width:80px"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($overrides as $ov): ?>
        <tr>
            <td><code><?= View::e($ov['feature_key']) ?></code></td>
            <td><?= View::e($ov['value_type']) ?></td>
            <td><?= View::e($ov['feature_value']) ?></td>
            <td style="font-size:0.85rem"><?= $ov['expires_at'] ? View::e(substr($ov['expires_at'], 0, 16)) : '—' ?></td>
            <td style="font-size:0.85rem;color:#6b7280"><?= $ov['note'] ? View::e($ov['note']) : '' ?></td>
            <td>
                <form method="post"
                      action="/admin/users/<?= (int) $user['id'] ?>/overrides/<?= (int) $ov['id'] ?>/delete"
                      style="display:inline"
                      onsubmit="return confirm('Delete override for <?= View::e($ov['feature_key']) ?>?')">
                    <?= CsrfService::field() ?>
                    <button type="submit" class="btn btn-danger"
                            style="padding:0.25rem 0.6rem;font-size:0.8rem">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php else: ?>
<p style="color:#888;margin-bottom:1rem">No feature overrides set.</p>
<?php endif; ?>

<h3 style="font-size:1rem;font-weight:600;margin-bottom:0.75rem">Add / Update Override</h3>
<p style="font-size:0.85rem;color:#6b7280;margin-bottom:1rem">
    If a key already exists for this user it will be replaced.
</p>

<form method="post" action="/admin/users/<?= (int) $user['id'] ?>/overrides" style="max-width:520px;margin-bottom:2rem">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label for="feature_key">Feature Key</label>
        <input type="text" id="feature_key" name="feature_key"
               value="<?= View::e($oldOverride['feature_key'] ?? '') ?>"
               placeholder="e.g. max_qr_codes" style="max-width:280px">
        <small style="display:block;color:#888;margin-top:0.2rem">
            Lowercase letters, digits, underscores; must start with a letter.
        </small>
    </div>

    <div class="form-group">
        <label for="value_type">Value Type</label>
        <select id="value_type" name="value_type"
                style="display:block;max-width:140px;padding:0.45rem 0.65rem;border:1px solid #ccc;border-radius:4px;font-size:0.95rem;background:#fff">
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
               style="max-width:320px">
    </div>

    <div class="form-group">
        <label for="expires_at">Expires At (optional)</label>
        <input type="datetime-local" id="expires_at" name="expires_at"
               value="<?= View::e($oldOverride['expires_at'] ?? '') ?>"
               style="max-width:220px">
        <small style="display:block;color:#888;margin-top:0.2rem">Leave blank for no expiry.</small>
    </div>

    <div class="form-group">
        <label for="override_note">Note (optional)</label>
        <input type="text" id="override_note" name="note" maxlength="255"
               value="<?= View::e($oldOverride['note'] ?? '') ?>"
               style="max-width:380px" placeholder="Reason for override…">
    </div>

    <div class="form-group" style="margin-top:1.1rem">
        <button type="submit" class="btn">Save Override</button>
    </div>
</form>
