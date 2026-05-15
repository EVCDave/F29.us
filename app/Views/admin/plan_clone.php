<div class="page-header">
    <h1>Clone Plan</h1>
    <a href="/admin/plans/<?= (int) $plan['id'] ?>" class="back-link">&larr; Plan Detail</a>
</div>

<div class="flash flash-info mw-580">
    Cloning <strong><?= View::e($plan['display_name']) ?></strong>
    (<code><?= View::e($plan['internal_name']) ?></code>).
    All features will be copied to the new plan.
    The source plan is not modified.
</div>

<?php if (!empty($errors)): ?>
<ul class="errors mw-520">
    <?php foreach ($errors as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/admin/plans/<?= (int) $plan['id'] ?>/clone" class="mw-520">
    <?= CsrfService::field() ?>

    <div class="form-group">
        <label for="internal_name">New Internal Name</label>
        <input type="text" id="internal_name" name="internal_name"
               value="<?= View::e($old['internal_name'] ?? '') ?>"
               placeholder="e.g. starter_v2" class="mw-280" autocomplete="off">
        <small class="hint">
            Must be unique. Lowercase letters, digits, underscores; must start with a letter.
            <strong>Cannot be changed after creation.</strong>
        </small>
    </div>

    <div class="form-group">
        <label for="display_name">New Display Name</label>
        <input type="text" id="display_name" name="display_name"
               value="<?= View::e($old['display_name'] ?? '') ?>"
               placeholder="e.g. Starter V2" class="mw-280">
    </div>

    <fieldset class="mw-440">
        <legend>Initial Flags</legend>
        <div class="d-flex flex-col gap-2 mt-1">
            <label class="d-flex align-center gap-2 fw-normal text-sm">
                <input type="checkbox" name="is_public" value="1"
                       <?= !empty($old['is_public']) ? 'checked' : '' ?>>
                Public — visible in public plan catalog
            </label>
            <label class="d-flex align-center gap-2 fw-normal text-sm">
                <input type="checkbox" name="is_active" value="1"
                       <?= (!array_key_exists('is_active', $old) || !empty($old['is_active'])) ? 'checked' : '' ?>>
                Active — available for admin assignment
            </label>
            <label class="d-flex align-center gap-2 fw-normal text-sm">
                <input type="checkbox" name="is_legacy" value="1"
                       <?= !empty($old['is_legacy']) ? 'checked' : '' ?>>
                Legacy — grandfathered plan
            </label>
        </div>
        <p class="text-82 text-muted mt-2 mb-0">
            Public is unchecked by default so the clone is not immediately customer-visible. Activate it when ready.
        </p>
    </fieldset>

    <p class="text-82 text-muted mb-5">
        Description, pricing, currency, and sort order are copied from the source plan.
        You can adjust them after cloning via Edit Metadata.
    </p>

    <div class="form-group">
        <button type="submit" class="btn">Clone Plan</button>
        <a href="/admin/plans/<?= (int) $plan['id'] ?>" class="btn btn-secondary ml-2">Cancel</a>
    </div>
</form>
