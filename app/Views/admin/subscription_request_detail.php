<?php
$isPending = $request['status'] === 'pending';
?>
<div class="page-header">
    <h1>Request #<?= (int) $request['id'] ?></h1>
    <a href="/admin/subscription-requests" class="back-link">&larr; Requests</a>
</div>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<!-- ── Request info ───────────────────────────────────────────────────────── -->
<table class="mw-520 mb-8">
    <tr>
        <th class="col-160">Status</th>
        <td class="status-<?= View::e($request['status']) ?>"><?= View::e($request['status']) ?></td>
    </tr>
    <tr>
        <th>Requested At</th>
        <td><?= View::e($request['requested_at']) ?></td>
    </tr>
    <tr>
        <th>Reviewed At</th>
        <td><?= $request['reviewed_at'] ? View::e($request['reviewed_at']) : '<span class="text-faint">—</span>' ?></td>
    </tr>
    <?php if ($request['reviewer_email']): ?>
    <tr>
        <th>Reviewed By</th>
        <td><?= View::e($request['reviewer_email']) ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($request['note']): ?>
    <tr>
        <th>Note</th>
        <td class="text-88"><?= View::e($request['note']) ?></td>
    </tr>
    <?php endif; ?>
</table>

<!-- ── User ──────────────────────────────────────────────────────────────── -->
<h2 class="mb-3">User</h2>
<table class="mw-520 mb-8">
    <tr>
        <th class="col-160">User ID</th>
        <td><a href="/admin/users/<?= (int) $request['user_id'] ?>"><?= (int) $request['user_id'] ?></a></td>
    </tr>
    <tr>
        <th>Email</th>
        <td><?= View::e($request['user_email']) ?></td>
    </tr>
    <tr>
        <th>Role</th>
        <td><?= View::e($request['user_role']) ?></td>
    </tr>
    <tr>
        <th>Account Status</th>
        <td class="status-<?= View::e($request['user_status']) ?>"><?= View::e($request['user_status']) ?></td>
    </tr>
</table>

<!-- ── Plans ─────────────────────────────────────────────────────────────── -->
<h2 class="mb-3">Plans</h2>
<table class="mw-520 mb-8">
    <tr>
        <th class="col-160">Current Plan</th>
        <td>
            <?php if ($request['current_plan_name']): ?>
                <?= View::e($request['current_plan_name']) ?>
                <span class="text-faint text-sm">(<?= View::e($request['current_plan_internal']) ?>)</span>
            <?php else: ?>
                <span class="text-faint">none</span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th>Requested Plan</th>
        <td>
            <strong><?= View::e($request['requested_plan_name']) ?></strong>
            <span class="text-faint text-sm">(<?= View::e($request['requested_plan_internal']) ?>)</span>
        </td>
    </tr>
    <tr>
        <th>Plan Flags</th>
        <td class="text-88">
            <?php
            $flags = [];
            if ($request['requested_plan_is_public'])  $flags[] = '<span class="text-success">public</span>';
            if ($request['requested_plan_is_active'])  $flags[] = '<span class="text-blue">active</span>';
            if ($request['requested_plan_is_legacy'])  $flags[] = '<span class="text-amber">legacy</span>';
            if (!$request['requested_plan_is_active'])
                $flags[] = '<span class="text-danger fw-medium">INACTIVE</span>';
            echo $flags ? implode(' &nbsp;', $flags) : '—';
            ?>
        </td>
    </tr>
</table>

<?php if (!$request['requested_plan_is_active'] || $request['requested_plan_is_legacy']): ?>
<div class="card-warn mw-520 mb-6">
    The requested plan is <?= !$request['requested_plan_is_active'] ? 'inactive' : 'legacy' ?>.
    Approval is blocked. Deny this request or handle it manually.
</div>
<?php endif; ?>

<!-- ── Actions ────────────────────────────────────────────────────────────── -->
<?php if ($isPending): ?>
<h2 class="mb-3">Actions</h2>
<div class="d-flex gap-6 flex-wrap align-start mw-680">

    <!-- Approve -->
    <div>
        <?php if ($request['requested_plan_is_active'] && !$request['requested_plan_is_legacy']): ?>
        <form method="post" action="/admin/subscription-requests/<?= (int) $request['id'] ?>/approve"
              data-confirm="Approve this request? The user's current subscription will be replaced.">
            <?= CsrfService::field() ?>
            <button type="submit" class="btn">Approve</button>
        </form>
        <p class="text-xs text-muted mt-1 mw-180">
            Closes current sub and activates requested plan.
        </p>
        <?php else: ?>
        <span class="btn-disabled" title="Plan is inactive or legacy">Approve</span>
        <p class="text-xs text-danger mt-1 mw-180">
            Plan is not approvable.
        </p>
        <?php endif; ?>
    </div>

    <!-- Deny -->
    <div>
        <form method="post" action="/admin/subscription-requests/<?= (int) $request['id'] ?>/deny">
            <?= CsrfService::field() ?>
            <div class="mb-2">
                <textarea name="note" rows="2" maxlength="1000" placeholder="Optional reason…"
                    class="mw-260 text-sm"></textarea>
            </div>
            <button type="submit" class="btn btn-secondary">Deny</button>
        </form>
        <p class="text-xs text-muted mt-1 mw-180">
            User's subscription is unchanged.
        </p>
    </div>

    <!-- Cancel -->
    <div>
        <form method="post" action="/admin/subscription-requests/<?= (int) $request['id'] ?>/cancel"
              data-confirm="Cancel this request?">
            <?= CsrfService::field() ?>
            <button type="submit" class="btn btn-secondary">Cancel Request</button>
        </form>
        <p class="text-xs text-muted mt-1 mw-180">
            Mark stale or erroneous requests as canceled.
        </p>
    </div>

</div>
<?php else: ?>
<p class="text-muted text-base mt-4">
    This request has already been <strong><?= View::e($request['status']) ?></strong>. No further actions available.
</p>
<?php endif; ?>
