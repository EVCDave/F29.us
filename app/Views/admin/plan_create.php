<div class="page-header">
    <h1>New Plan</h1>
    <a href="/admin/plans" class="back-link">&larr; Plans</a>
</div>

<?php if (!empty($errors)): ?>
<ul class="errors mw-520">
    <?php foreach ($errors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/admin/plans" class="mw-520">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label for="internal_name">Internal Name</label>
        <input type="text" id="internal_name" name="internal_name"
               value="<?= View::e($old['internal_name'] ?? '') ?>"
               placeholder="e.g. pro_v2" class="mw-280" autocomplete="off">
        <small class="hint">
            Lowercase letters, digits, underscores; must start with a letter. <strong>Cannot be changed after creation.</strong>
        </small>
    </div>

    <div class="form-group">
        <label for="display_name">Display Name</label>
        <input type="text" id="display_name" name="display_name"
               value="<?= View::e($old['display_name'] ?? '') ?>"
               placeholder="e.g. Pro" class="mw-280">
    </div>

    <div class="form-group">
        <label for="description">Description (optional)</label>
        <textarea id="description" name="description" rows="3" maxlength="1000"
        ><?= View::e($old['description'] ?? '') ?></textarea>
    </div>

    <div class="d-flex gap-4 flex-wrap">
        <div class="form-group">
            <label for="monthly_price_cents">Monthly Price (cents)</label>
            <input type="text" id="monthly_price_cents" name="monthly_price_cents"
                   value="<?= View::e($old['monthly_price_cents'] ?? '') ?>"
                   placeholder="e.g. 999" class="mw-140">
            <small class="hint">Leave blank for free / N/A.</small>
        </div>

        <div class="form-group">
            <label for="yearly_price_cents">Yearly Price (cents)</label>
            <input type="text" id="yearly_price_cents" name="yearly_price_cents"
                   value="<?= View::e($old['yearly_price_cents'] ?? '') ?>"
                   placeholder="e.g. 9990" class="mw-140">
            <small class="hint">Leave blank for free / N/A.</small>
        </div>
    </div>

    <div class="form-group">
        <label for="currency_code">Currency Code</label>
        <input type="text" id="currency_code" name="currency_code"
               value="<?= View::e($old['currency_code'] ?? 'USD') ?>"
               placeholder="USD" class="mw-80" maxlength="3">
        <small class="hint">3-letter ISO code (e.g. USD, EUR).</small>
    </div>

    <div class="form-group">
        <label for="sort_order">Sort Order</label>
        <input type="text" id="sort_order" name="sort_order"
               value="<?= View::e($old['sort_order'] ?? '0') ?>"
               placeholder="0" class="mw-80">
        <small class="hint">Lower numbers appear first.</small>
    </div>

    <div class="d-flex flex-col gap-2 mb-4">
        <label class="d-flex align-center gap-2 fw-normal">
            <input type="checkbox" name="is_public" value="1"
                   <?= !empty($old) ? (!empty($old['is_public']) ? 'checked' : '') : 'checked' ?>>
            Public — visible in public plan catalog
        </label>
        <label class="d-flex align-center gap-2 fw-normal">
            <input type="checkbox" name="is_active" value="1"
                   <?= !empty($old) ? (!empty($old['is_active']) ? 'checked' : '') : 'checked' ?>>
            Active — available for admin assignment
        </label>
        <label class="d-flex align-center gap-2 fw-normal">
            <input type="checkbox" name="is_legacy" value="1"
                   <?= !empty($old['is_legacy']) ? 'checked' : '' ?>>
            Legacy — grandfathered plan (not offered to new users)
        </label>
    </div>

    <div class="form-group mt-4">
        <button type="submit" class="btn">Create Plan</button>
        <a href="/admin/plans" class="btn btn-secondary ml-2">Cancel</a>
    </div>
</form>
