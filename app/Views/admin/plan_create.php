<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1.25rem">
    <h1>New Plan</h1>
    <a href="/admin/plans" style="color:#666;font-size:0.9rem">&larr; Plans</a>
</div>

<?php if (!empty($errors)): ?>
<ul class="errors" style="max-width:520px">
    <?php foreach ($errors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/admin/plans" style="max-width:520px">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label for="internal_name">Internal Name</label>
        <input type="text" id="internal_name" name="internal_name"
               value="<?= View::e($old['internal_name'] ?? '') ?>"
               placeholder="e.g. pro_v2" style="max-width:280px" autocomplete="off">
        <small style="display:block;color:#888;margin-top:0.2rem">
            Lowercase letters, digits, underscores; must start with a letter. <strong>Cannot be changed after creation.</strong>
        </small>
    </div>

    <div class="form-group">
        <label for="display_name">Display Name</label>
        <input type="text" id="display_name" name="display_name"
               value="<?= View::e($old['display_name'] ?? '') ?>"
               placeholder="e.g. Pro" style="max-width:280px">
    </div>

    <div class="form-group">
        <label for="description">Description (optional)</label>
        <textarea id="description" name="description" rows="3" maxlength="1000"
            style="display:block;width:100%;max-width:520px;padding:0.45rem 0.65rem;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;resize:vertical"
        ><?= View::e($old['description'] ?? '') ?></textarea>
    </div>

    <div style="display:flex;gap:1rem;flex-wrap:wrap">
        <div class="form-group">
            <label for="monthly_price_cents">Monthly Price (cents)</label>
            <input type="text" id="monthly_price_cents" name="monthly_price_cents"
                   value="<?= View::e($old['monthly_price_cents'] ?? '') ?>"
                   placeholder="e.g. 999" style="max-width:140px">
            <small style="display:block;color:#888;margin-top:0.2rem">Leave blank for free / N/A.</small>
        </div>

        <div class="form-group">
            <label for="yearly_price_cents">Yearly Price (cents)</label>
            <input type="text" id="yearly_price_cents" name="yearly_price_cents"
                   value="<?= View::e($old['yearly_price_cents'] ?? '') ?>"
                   placeholder="e.g. 9990" style="max-width:140px">
            <small style="display:block;color:#888;margin-top:0.2rem">Leave blank for free / N/A.</small>
        </div>
    </div>

    <div class="form-group">
        <label for="currency_code">Currency Code</label>
        <input type="text" id="currency_code" name="currency_code"
               value="<?= View::e($old['currency_code'] ?? 'USD') ?>"
               placeholder="USD" style="max-width:80px" maxlength="3">
        <small style="display:block;color:#888;margin-top:0.2rem">3-letter ISO code (e.g. USD, EUR).</small>
    </div>

    <div class="form-group">
        <label for="sort_order">Sort Order</label>
        <input type="text" id="sort_order" name="sort_order"
               value="<?= View::e($old['sort_order'] ?? '0') ?>"
               placeholder="0" style="max-width:80px">
        <small style="display:block;color:#888;margin-top:0.2rem">Lower numbers appear first.</small>
    </div>

    <div style="display:flex;flex-direction:column;gap:0.5rem;margin-bottom:1rem">
        <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400">
            <input type="checkbox" name="is_public" value="1"
                   <?= !empty($old) ? (!empty($old['is_public']) ? 'checked' : '') : 'checked' ?>>
            Public — visible in public plan catalog
        </label>
        <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400">
            <input type="checkbox" name="is_active" value="1"
                   <?= !empty($old) ? (!empty($old['is_active']) ? 'checked' : '') : 'checked' ?>>
            Active — available for admin assignment
        </label>
        <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400">
            <input type="checkbox" name="is_legacy" value="1"
                   <?= !empty($old['is_legacy']) ? 'checked' : '' ?>>
            Legacy — grandfathered plan (not offered to new users)
        </label>
    </div>

    <div class="form-group" style="margin-top:1.1rem">
        <button type="submit" class="btn">Create Plan</button>
        <a href="/admin/plans" class="btn btn-secondary" style="margin-left:0.5rem">Cancel</a>
    </div>
</form>
