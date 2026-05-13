<?php
$isPending = $request['status'] === 'pending';
$statusColors = [
    'pending'  => 'color:#92400e;font-weight:500',
    'approved' => 'color:#166534;font-weight:500',
    'denied'   => 'color:#991b1b;font-weight:500',
    'canceled' => 'color:#6b7280',
];
$statusStyle = $statusColors[$request['status']] ?? '';
?>
<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1.25rem">
    <h1>Request #<?= (int) $request['id'] ?></h1>
    <a href="/admin/subscription-requests" style="color:#666;font-size:0.9rem">&larr; Requests</a>
</div>

<?php if ($flash): ?>
<div class="notice" style="display:block;margin-bottom:1.25rem;
    <?= $flash['type'] === 'error'   ? 'background:#fef2f2;border-color:#fca5a5;color:#991b1b;' : '' ?>
    <?= $flash['type'] === 'success' ? 'background:#f0fdf4;border-color:#86efac;color:#166534;' : '' ?>">
    <?= View::e($flash['text']) ?>
</div>
<?php endif; ?>

<!-- ── Request info ───────────────────────────────────────────────────────── -->
<table style="max-width:520px;margin-bottom:2rem">
    <tr>
        <th style="width:160px">Status</th>
        <td><span style="<?= $statusStyle ?>"><?= View::e($request['status']) ?></span></td>
    </tr>
    <tr>
        <th>Requested At</th>
        <td><?= View::e($request['requested_at']) ?></td>
    </tr>
    <tr>
        <th>Reviewed At</th>
        <td><?= $request['reviewed_at'] ? View::e($request['reviewed_at']) : '<span style="color:#9ca3af">—</span>' ?></td>
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
        <td style="font-size:0.88rem;color:#374151"><?= View::e($request['note']) ?></td>
    </tr>
    <?php endif; ?>
</table>

<!-- ── User ──────────────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.6rem">User</h2>
<table style="max-width:520px;margin-bottom:2rem">
    <tr>
        <th style="width:160px">User ID</th>
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
<h2 style="margin-bottom:0.6rem">Plans</h2>
<table style="max-width:520px;margin-bottom:2rem">
    <tr>
        <th style="width:160px">Current Plan</th>
        <td>
            <?php if ($request['current_plan_name']): ?>
                <?= View::e($request['current_plan_name']) ?>
                <span style="color:#9ca3af;font-size:0.85rem">(<?= View::e($request['current_plan_internal']) ?>)</span>
            <?php else: ?>
                <span style="color:#9ca3af">none</span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th>Requested Plan</th>
        <td>
            <strong><?= View::e($request['requested_plan_name']) ?></strong>
            <span style="color:#9ca3af;font-size:0.85rem">(<?= View::e($request['requested_plan_internal']) ?>)</span>
        </td>
    </tr>
    <tr>
        <th>Plan Flags</th>
        <td style="font-size:0.88rem">
            <?php
            $flags = [];
            if ($request['requested_plan_is_public'])  $flags[] = '<span style="color:#166534">public</span>';
            if ($request['requested_plan_is_active'])  $flags[] = '<span style="color:#1d4ed8">active</span>';
            if ($request['requested_plan_is_legacy'])  $flags[] = '<span style="color:#d97706">legacy</span>';
            if (!$request['requested_plan_is_active'])
                $flags[] = '<span style="color:#991b1b;font-weight:500">INACTIVE</span>';
            echo $flags ? implode(' &nbsp;', $flags) : '—';
            ?>
        </td>
    </tr>
</table>

<?php if (!$request['requested_plan_is_active'] || $request['requested_plan_is_legacy']): ?>
<div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:4px;padding:0.65rem 0.9rem;
            max-width:520px;margin-bottom:1.5rem;font-size:0.88rem">
    The requested plan is <?= !$request['requested_plan_is_active'] ? 'inactive' : 'legacy' ?>.
    Approval is blocked. Deny this request or handle it manually.
</div>
<?php endif; ?>

<!-- ── Actions ────────────────────────────────────────────────────────────── -->
<?php if ($isPending): ?>
<h2 style="margin-bottom:0.75rem">Actions</h2>
<div style="display:flex;gap:1.5rem;flex-wrap:wrap;align-items:flex-start;max-width:640px">

    <!-- Approve -->
    <div>
        <?php if ($request['requested_plan_is_active'] && !$request['requested_plan_is_legacy']): ?>
        <form method="post" action="/admin/subscription-requests/<?= (int) $request['id'] ?>/approve"
              onsubmit="return confirm('Approve this request? The user\'s current subscription will be replaced.')">
            <?= CsrfService::field() ?>
            <button type="submit" class="btn">Approve</button>
        </form>
        <p style="font-size:0.78rem;color:#6b7280;margin-top:0.3rem;max-width:160px">
            Closes current sub and activates requested plan.
        </p>
        <?php else: ?>
        <span class="btn-disabled" title="Plan is inactive or legacy">Approve</span>
        <p style="font-size:0.78rem;color:#991b1b;margin-top:0.3rem;max-width:160px">
            Plan is not approvable.
        </p>
        <?php endif; ?>
    </div>

    <!-- Deny -->
    <div>
        <form method="post" action="/admin/subscription-requests/<?= (int) $request['id'] ?>/deny">
            <?= CsrfService::field() ?>
            <div style="margin-bottom:0.5rem">
                <textarea name="note" rows="2" maxlength="1000" placeholder="Optional reason…"
                    style="display:block;width:100%;max-width:260px;padding:0.35rem 0.5rem;
                           border:1px solid #ccc;border-radius:4px;font-size:0.85rem;resize:vertical"
                ></textarea>
            </div>
            <button type="submit" class="btn btn-secondary">Deny</button>
        </form>
        <p style="font-size:0.78rem;color:#6b7280;margin-top:0.3rem;max-width:180px">
            User's subscription is unchanged.
        </p>
    </div>

    <!-- Cancel -->
    <div>
        <form method="post" action="/admin/subscription-requests/<?= (int) $request['id'] ?>/cancel"
              onsubmit="return confirm('Cancel this request?')">
            <?= CsrfService::field() ?>
            <button type="submit" class="btn btn-secondary">Cancel Request</button>
        </form>
        <p style="font-size:0.78rem;color:#6b7280;margin-top:0.3rem;max-width:180px">
            Mark stale or erroneous requests as canceled.
        </p>
    </div>

</div>
<?php else: ?>
<p style="color:#6b7280;font-size:0.9rem;margin-top:1rem">
    This request has already been <strong><?= View::e($request['status']) ?></strong>. No further actions available.
</p>
<?php endif; ?>
