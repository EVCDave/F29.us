<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1.25rem">
    <h1>Clone Plan</h1>
    <a href="/admin/plans/<?= (int) $plan['id'] ?>" style="color:#666;font-size:0.9rem">&larr; Plan Detail</a>
</div>

<div style="background:#f0f4ff;border:1px solid #c7d2fe;border-radius:4px;padding:0.75rem 1rem;margin-bottom:1.5rem;max-width:580px;font-size:0.9rem">
    Cloning <strong><?= View::e($plan['display_name']) ?></strong>
    (<code><?= View::e($plan['internal_name']) ?></code>).
    All <?= count($plan) > 0 ? '' : '' ?>features will be copied to the new plan.
    The source plan is not modified.
</div>

<?php if (!empty($errors)): ?>
<ul class="errors" style="max-width:520px">
    <?php foreach ($errors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/admin/plans/<?= (int) $plan['id'] ?>/clone" style="max-width:520px">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label for="internal_name">New Internal Name</label>
        <input type="text" id="internal_name" name="internal_name"
               value="<?= View::e($old['internal_name'] ?? '') ?>"
               placeholder="e.g. starter_v2" style="max-width:280px" autocomplete="off">
        <small style="display:block;color:#888;margin-top:0.2rem">
            Must be unique. Lowercase letters, digits, underscores; must start with a letter.
            <strong>Cannot be changed after creation.</strong>
        </small>
    </div>

    <div class="form-group">
        <label for="display_name">New Display Name</label>
        <input type="text" id="display_name" name="display_name"
               value="<?= View::e($old['display_name'] ?? '') ?>"
               placeholder="e.g. Starter V2" style="max-width:280px">
    </div>

    <fieldset style="border:1px solid #e5e7eb;border-radius:4px;padding:0.75rem 1rem;margin-bottom:1rem;max-width:440px">
        <legend style="font-size:0.85rem;font-weight:600;padding:0 0.3rem;color:#374151">Initial Flags</legend>
        <div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:0.25rem">
            <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400;font-size:0.9rem">
                <input type="checkbox" name="is_public" value="1"
                       <?= !empty($old['is_public']) ? 'checked' : '' ?>>
                Public — visible in public plan catalog
            </label>
            <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400;font-size:0.9rem">
                <input type="checkbox" name="is_active" value="1"
                       <?= (!array_key_exists('is_active', $old) || !empty($old['is_active'])) ? 'checked' : '' ?>>
                Active — available for admin assignment
            </label>
            <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400;font-size:0.9rem">
                <input type="checkbox" name="is_legacy" value="1"
                       <?= !empty($old['is_legacy']) ? 'checked' : '' ?>>
                Legacy — grandfathered plan
            </label>
        </div>
        <p style="font-size:0.8rem;color:#6b7280;margin-top:0.5rem;margin-bottom:0">
            Public is unchecked by default so the clone is not immediately customer-visible. Activate it when ready.
        </p>
    </fieldset>

    <p style="font-size:0.82rem;color:#6b7280;margin-bottom:1.25rem">
        Description, pricing, currency, and sort order are copied from the source plan.
        You can adjust them after cloning via Edit Metadata.
    </p>

    <div class="form-group">
        <button type="submit" class="btn">Clone Plan</button>
        <a href="/admin/plans/<?= (int) $plan['id'] ?>" class="btn btn-secondary" style="margin-left:0.5rem">Cancel</a>
    </div>
</form>
