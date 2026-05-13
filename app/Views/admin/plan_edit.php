<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1.25rem">
    <h1>Edit Plan</h1>
    <a href="/admin/plans/<?= (int) $plan['id'] ?>" style="color:#666;font-size:0.9rem">&larr; Plan Detail</a>
</div>

<?php if ($subActive > 0): ?>
<div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:4px;padding:0.75rem 1rem;margin-bottom:1.25rem;max-width:580px">
    <strong><?= $subActive ?> active subscription<?= $subActive !== 1 ? 's' : '' ?> use this plan.</strong>
    Saving these changes affects all of them immediately.
    To adjust the offer for future users only, <a href="/admin/plans/<?= (int) $plan['id'] ?>/clone">clone this plan</a> into a new version instead.
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<ul class="errors" style="max-width:520px">
    <?php foreach ($errors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<?php
$v = static function (string $key) use ($old, $plan): string {
    return View::e((string) (array_key_exists($key, $old) ? ($old[$key] ?? '') : ($plan[$key] ?? '')));
};
$checked = static function (string $key) use ($old, $plan): string {
    $val = array_key_exists($key, $old) ? !empty($old[$key]) : (bool) $plan[$key];
    return $val ? 'checked' : '';
};
?>

<form method="post" action="/admin/plans/<?= (int) $plan['id'] ?>/update" style="max-width:520px">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label>Internal Name</label>
        <input type="text" value="<?= View::e($plan['internal_name']) ?>" disabled
               style="max-width:280px;background:#f3f4f6;color:#6b7280;cursor:not-allowed">
        <small style="display:block;color:#888;margin-top:0.2rem">Cannot be changed after creation.</small>
    </div>

    <div class="form-group">
        <label for="display_name">Display Name</label>
        <input type="text" id="display_name" name="display_name"
               value="<?= $v('display_name') ?>"
               placeholder="e.g. Pro" style="max-width:280px">
    </div>

    <div class="form-group">
        <label for="description">Description (optional)</label>
        <textarea id="description" name="description" rows="3" maxlength="1000"
            style="display:block;width:100%;max-width:520px;padding:0.45rem 0.65rem;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;resize:vertical"
        ><?= $v('description') ?></textarea>
    </div>

    <div style="display:flex;gap:1rem;flex-wrap:wrap">
        <div class="form-group">
            <label for="monthly_price_cents">Monthly Price (cents)</label>
            <input type="text" id="monthly_price_cents" name="monthly_price_cents"
                   value="<?= $v('monthly_price_cents') ?>"
                   placeholder="e.g. 999" style="max-width:140px">
            <small style="display:block;color:#888;margin-top:0.2rem">Leave blank for free / N/A.</small>
        </div>

        <div class="form-group">
            <label for="yearly_price_cents">Yearly Price (cents)</label>
            <input type="text" id="yearly_price_cents" name="yearly_price_cents"
                   value="<?= $v('yearly_price_cents') ?>"
                   placeholder="e.g. 9990" style="max-width:140px">
            <small style="display:block;color:#888;margin-top:0.2rem">Leave blank for free / N/A.</small>
        </div>
    </div>

    <div class="form-group">
        <label for="currency_code">Currency Code</label>
        <input type="text" id="currency_code" name="currency_code"
               value="<?= $v('currency_code') ?>"
               placeholder="USD" style="max-width:80px" maxlength="3">
        <small style="display:block;color:#888;margin-top:0.2rem">3-letter ISO code (e.g. USD, EUR).</small>
    </div>

    <div class="form-group">
        <label for="sort_order">Sort Order</label>
        <input type="text" id="sort_order" name="sort_order"
               value="<?= $v('sort_order') ?>"
               placeholder="0" style="max-width:80px">
        <small style="display:block;color:#888;margin-top:0.2rem">Lower numbers appear first.</small>
    </div>

    <div style="display:flex;flex-direction:column;gap:0.5rem;margin-bottom:1rem">
        <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400">
            <input type="checkbox" name="is_public" value="1" <?= $checked('is_public') ?>>
            Public — visible in public plan catalog
        </label>
        <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400">
            <input type="checkbox" name="is_active" value="1" <?= $checked('is_active') ?>>
            Active — available for admin assignment
        </label>
        <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400">
            <input type="checkbox" name="is_legacy" value="1" <?= $checked('is_legacy') ?>>
            Legacy — grandfathered plan (not offered to new users)
        </label>
    </div>

    <div class="form-group" style="margin-top:1.1rem">
        <button type="submit" class="btn">Save Changes</button>
        <a href="/admin/plans/<?= (int) $plan['id'] ?>" class="btn btn-secondary" style="margin-left:0.5rem">Cancel</a>
    </div>
</form>
