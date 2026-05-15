<div class="page-header">
    <h1>Edit Plan</h1>
    <a href="/admin/plans/<?= (int) $plan['id'] ?>" class="back-link">&larr; Plan Detail</a>
</div>

<?php if ($subActive > 0): ?>
<div class="card-warn mw-580 mb-5">
    <strong><?= $subActive ?> active subscription<?= $subActive !== 1 ? 's' : '' ?> use this plan.</strong>
    Saving these changes affects all of them immediately.
    To adjust the offer for future users only, <a href="/admin/plans/<?= (int) $plan['id'] ?>/clone">clone this plan</a> into a new version instead.
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<ul class="errors mw-520">
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

<form method="post" action="/admin/plans/<?= (int) $plan['id'] ?>/update" class="mw-520">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label>Internal Name</label>
        <input type="text" value="<?= View::e($plan['internal_name']) ?>" disabled class="mw-280">
        <small class="hint">Cannot be changed after creation.</small>
    </div>

    <div class="form-group">
        <label for="display_name">Display Name</label>
        <input type="text" id="display_name" name="display_name"
               value="<?= $v('display_name') ?>"
               placeholder="e.g. Pro" class="mw-280">
    </div>

    <div class="form-group">
        <label for="description">Description (optional)</label>
        <textarea id="description" name="description" rows="3" maxlength="1000"
        ><?= $v('description') ?></textarea>
    </div>

    <div class="d-flex gap-4 flex-wrap">
        <div class="form-group">
            <label for="monthly_price_cents">Monthly Price (cents)</label>
            <input type="text" id="monthly_price_cents" name="monthly_price_cents"
                   value="<?= $v('monthly_price_cents') ?>"
                   placeholder="e.g. 999" class="mw-140">
            <small class="hint">Leave blank for free / N/A.</small>
        </div>

        <div class="form-group">
            <label for="yearly_price_cents">Yearly Price (cents)</label>
            <input type="text" id="yearly_price_cents" name="yearly_price_cents"
                   value="<?= $v('yearly_price_cents') ?>"
                   placeholder="e.g. 9990" class="mw-140">
            <small class="hint">Leave blank for free / N/A.</small>
        </div>
    </div>

    <div class="form-group">
        <label for="currency_code">Currency Code</label>
        <input type="text" id="currency_code" name="currency_code"
               value="<?= $v('currency_code') ?>"
               placeholder="USD" class="mw-80" maxlength="3">
        <small class="hint">3-letter ISO code (e.g. USD, EUR).</small>
    </div>

    <div class="form-group">
        <label for="sort_order">Sort Order</label>
        <input type="text" id="sort_order" name="sort_order"
               value="<?= $v('sort_order') ?>"
               placeholder="0" class="mw-80">
        <small class="hint">Lower numbers appear first.</small>
    </div>

    <div class="d-flex flex-col gap-2 mb-4">
        <label class="d-flex align-center gap-2 fw-normal">
            <input type="checkbox" name="is_public" value="1" <?= $checked('is_public') ?>>
            Public — visible in public plan catalog
        </label>
        <label class="d-flex align-center gap-2 fw-normal">
            <input type="checkbox" name="is_active" value="1" <?= $checked('is_active') ?>>
            Active — available for admin assignment
        </label>
        <label class="d-flex align-center gap-2 fw-normal">
            <input type="checkbox" name="is_legacy" value="1" <?= $checked('is_legacy') ?>>
            Legacy — grandfathered plan (not offered to new users)
        </label>
    </div>

    <div class="form-group mt-4">
        <button type="submit" class="btn">Save Changes</button>
        <a href="/admin/plans/<?= (int) $plan['id'] ?>" class="btn btn-secondary ml-2">Cancel</a>
    </div>
</form>
