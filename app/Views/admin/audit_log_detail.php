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
<div class="page-header">
    <h1>Audit Log #<?= (int) $log['id'] ?></h1>
    <a href="/admin/audit-logs" class="back-link">&larr; Audit Logs</a>
</div>

<!-- ── Core fields ────────────────────────────────────────────────────────── -->
<table class="mw-560 mb-8">
    <tr>
        <th class="col-160">ID</th>
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
                <span class="text-faint text-sm">(#<?= (int) $log['user_id'] ?>)</span>
            <?php else: ?>
                <span class="text-faint">system</span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th>Entity Type</th>
        <td class="monospace"><?= View::e($log['entity_type']) ?></td>
    </tr>
    <tr>
        <th>Entity ID</th>
        <td><?= (int) $log['entity_id'] ?></td>
    </tr>
    <tr>
        <th>Action</th>
        <td class="monospace fw-bold"><?= View::e($log['action']) ?></td>
    </tr>
</table>

<!-- ── Metadata ───────────────────────────────────────────────────────────── -->
<h2 class="mb-3">Metadata</h2>
<?php if ($meta !== null): ?>
<pre class="filter-panel mw-680 text-83"><?= View::e(
    json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
) ?></pre>
<?php else: ?>
<p class="text-faint text-base mb-6">No metadata recorded.</p>
<?php endif; ?>

<!-- ── Related links ─────────────────────────────────────────────────────── -->
<?php if (!empty($relatedLinks)): ?>
<h2 class="mt-6 mb-3">Related</h2>
<div class="actions-group">
    <?php foreach ($relatedLinks as $link): ?>
    <a href="<?= View::e($link['href']) ?>" class="btn btn-secondary btn-sm">
        <?= View::e($link['label']) ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
