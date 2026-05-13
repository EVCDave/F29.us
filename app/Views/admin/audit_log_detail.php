<?php
// Build related links based on entity type and metadata
$relatedLinks = [];

if ($log['user_id']) {
    $relatedLinks[] = [
        'label' => 'Acting User #' . (int) $log['user_id'],
        'href'  => '/admin/users/' . (int) $log['user_id'],
    ];
}

switch ($log['entity_type']) {
    case 'plan':
        $relatedLinks[] = ['label' => 'Plan #' . (int) $log['entity_id'], 'href' => '/admin/plans/' . (int) $log['entity_id']];
        break;

    case 'plan_feature':
        $planId = (int) ($meta['plan_id'] ?? 0);
        if ($planId > 0) {
            $relatedLinks[] = ['label' => 'Plan #' . $planId, 'href' => '/admin/plans/' . $planId];
        }
        break;

    case 'subscription_change_request':
        $relatedLinks[] = ['label' => 'Request #' . (int) $log['entity_id'], 'href' => '/admin/subscription-requests/' . (int) $log['entity_id']];
        $targetUser = (int) ($meta['target_user_id'] ?? 0);
        if ($targetUser > 0) {
            $relatedLinks[] = ['label' => 'Target User #' . $targetUser, 'href' => '/admin/users/' . $targetUser];
        }
        break;

    case 'user_subscription':
        $targetUser = (int) ($meta['target_user_id'] ?? 0);
        if ($targetUser > 0) {
            $relatedLinks[] = ['label' => 'Target User #' . $targetUser, 'href' => '/admin/users/' . $targetUser];
        }
        break;

    case 'user_feature_override':
        $targetUser = (int) ($meta['target_user_id'] ?? 0);
        if ($targetUser > 0) {
            $relatedLinks[] = ['label' => 'Target User #' . $targetUser, 'href' => '/admin/users/' . $targetUser];
        }
        break;

    case 'qr_code':
        $relatedLinks[] = ['label' => 'QR Code #' . (int) $log['entity_id'], 'href' => '/qr/' . (int) $log['entity_id']];
        break;
}
?>
<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1.25rem">
    <h1>Audit Log #<?= (int) $log['id'] ?></h1>
    <a href="/admin/audit-logs" style="color:#666;font-size:0.9rem">&larr; Audit Logs</a>
</div>

<!-- ── Core fields ────────────────────────────────────────────────────────── -->
<table style="max-width:560px;margin-bottom:2rem">
    <tr>
        <th style="width:160px">ID</th>
        <td><?= (int) $log['id'] ?></td>
    </tr>
    <tr>
        <th>Timestamp</th>
        <td><?= View::e($log['created_at']) ?></td>
    </tr>
    <tr>
        <th>Acting User</th>
        <td>
            <?php if ($log['user_id']): ?>
                <a href="/admin/users/<?= (int) $log['user_id'] ?>"><?= View::e($log['user_email'] ?? '') ?></a>
                <span style="color:#9ca3af;font-size:0.85rem">(#<?= (int) $log['user_id'] ?>)</span>
            <?php else: ?>
                <span style="color:#9ca3af">system</span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th>Entity Type</th>
        <td style="font-family:monospace"><?= View::e($log['entity_type']) ?></td>
    </tr>
    <tr>
        <th>Entity ID</th>
        <td><?= (int) $log['entity_id'] ?></td>
    </tr>
    <tr>
        <th>Action</th>
        <td style="font-family:monospace;font-weight:600"><?= View::e($log['action']) ?></td>
    </tr>
</table>

<!-- ── Metadata ───────────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.6rem">Metadata</h2>
<?php if ($meta !== null): ?>
<pre style="background:#f8f9fa;border:1px solid #e5e7eb;border-radius:4px;
            padding:0.85rem 1rem;font-size:0.83rem;overflow-x:auto;
            max-width:680px;line-height:1.5;white-space:pre-wrap;word-break:break-word"><?= View::e(
    json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
) ?></pre>
<?php else: ?>
<p style="color:#9ca3af;font-size:0.9rem;margin-bottom:1.5rem">No metadata recorded.</p>
<?php endif; ?>

<!-- ── Related links ─────────────────────────────────────────────────────── -->
<?php if (!empty($relatedLinks)): ?>
<h2 style="margin-top:1.5rem;margin-bottom:0.6rem">Related</h2>
<div class="actions-group">
    <?php foreach ($relatedLinks as $link): ?>
    <a href="<?= View::e($link['href']) ?>" class="btn btn-secondary"
       style="font-size:0.85rem;padding:0.3rem 0.8rem">
        <?= View::e($link['label']) ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
