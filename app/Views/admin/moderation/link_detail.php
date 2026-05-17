<?php
$qrId               = $link['qr_id']    !== null ? (int) $link['qr_id'] : null;
$ownerId            = $link['owner_id'] !== null ? (int) $link['owner_id'] : null;
$status             = (string) $link['status'];
$destinationDomain  = $destinationDomain ?? null;
$relatedAbuseCount  = count($relatedAbuseReports ?? []);
$auditEntries       = $auditEntries       ?? [];
$destinationHistory = $destinationHistory ?? [];
?>
<div class="page-header">
    <h1>Link #<?= (int) $link['id'] ?> — Moderation</h1>
    <a href="/admin/moderation/links" class="back-link">&larr; Moderated Links</a>
</div>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<?php if ($relatedAbuseCount > 0): ?>
<div class="card-warn mb-4">
    <p class="fw-medium mb-1">
        <?= $relatedAbuseCount === 1 ? '1 abuse report references this link.' : ($relatedAbuseCount . ' abuse reports reference this link.') ?>
    </p>
    <p class="text-2xs text-muted-2 mb-0">
        See the "Related abuse reports" section below before taking action.
    </p>
</div>
<?php endif; ?>

<!-- ── Link info ───────────────────────────────────────────────────────────── -->
<div class="card mb-6">
    <h2 class="mb-3">Link Details</h2>
    <table class="info-table info-table-w160 mb-0 text-88">
        <tbody>
            <tr>
                <td>Short link ID</td>
                <td><?= (int) $link['id'] ?></td>
            </tr>
            <tr>
                <td>Slug</td>
                <td><code><?= View::e($link['slug']) ?></code></td>
            </tr>
            <?php if ($link['qr_name'] !== null): ?>
            <tr>
                <td>QR name</td>
                <td>
                    <?= View::e($link['qr_name']) ?>
                    <?php if ($qrId !== null): ?>
                    <a href="/qr/<?= $qrId ?>" class="text-xs ml-2" title="User QR detail page">&nearr; QR detail</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>Owner</td>
                <td>
                    <?php if ($ownerId !== null): ?>
                    <a href="/admin/users/<?= $ownerId ?>"><?= View::e($link['owner_email'] ?? '—') ?></a>
                    <?php else: ?>
                    <?= View::e($link['owner_email'] ?? '—') ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>Current destination</td>
                <td class="word-break">
                    <a href="<?= View::e($link['current_target_url']) ?>" target="_blank">
                        <?= View::e($link['current_target_url']) ?>
                    </a>
                    <?php if ($destinationDomain !== null): ?>
                    <span class="text-2xs text-muted-2 d-block">domain: <code><?= View::e($destinationDomain) ?></code><?php
                        if (!empty($domainAlreadyBlocked)): ?> &middot; <span class="text-warning">already on blocklist</span><?php endif; ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>Status</td>
                <td>
                    <span class="status-<?= View::e($status) ?>"><?= View::e(ucfirst($status)) ?></span>
                </td>
            </tr>
            <tr>
                <td>Created at</td>
                <td><?= View::e($link['created_at']) ?> UTC</td>
            </tr>
            <tr>
                <td>Scans (total)</td>
                <td><?= (int) $totalScans ?></td>
            </tr>
            <tr>
                <td>Scans (24 h / 7 d)</td>
                <td><?= (int) $scans24h ?> / <?= (int) $scans7d ?></td>
            </tr>
        </tbody>
    </table>

    <?php if ($qrId !== null): ?>
    <div class="mt-3">
        <a href="/qr/<?= $qrId ?>/analytics" class="btn btn-secondary">View Analytics</a>
    </div>
    <?php endif; ?>
</div>

<!-- ── Moderation metadata ────────────────────────────────────────────────── -->
<?php if ($status === 'disabled'): ?>
<div class="card-danger mb-6">
    <h2 class="mb-3 text-danger">Disabled</h2>
    <table class="info-table info-table-w160 mb-0 text-88">
        <tbody>
            <tr>
                <td>Disabled reason</td>
                <td class="text-danger fw-medium"><?= View::e($link['disabled_reason'] ?? '—') ?></td>
            </tr>
            <tr>
                <td>Disabled at</td>
                <td><?= View::e($link['disabled_at'] ?? '—') ?> UTC</td>
            </tr>
            <tr>
                <td>Disabled by</td>
                <td><?= View::e($link['disabled_by_email'] ?? '—') ?></td>
            </tr>
            <?php if ($link['moderation_note']): ?>
            <tr>
                <td>Internal note</td>
                <td><?= View::e($link['moderation_note']) ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ── Status action ──────────────────────────────────────────────────────── -->
<?php if ($status === 'disabled'): ?>
<div class="card mw-520 mb-6">
    <h2 class="mb-2">Restore Link</h2>
    <p class="text-sm text-muted mb-4">
        Restoring sets status back to <strong>active</strong>. Moderation metadata is preserved for audit context.
    </p>
    <form method="post" action="/admin/moderation/links/<?= (int) $link['id'] ?>/restore"
          data-confirm="Restore this link to active?">
        <?= CsrfService::field() ?>
        <button type="submit" class="btn">Restore to Active</button>
    </form>
</div>
<?php else: ?>
<div class="card-danger mw-520 mb-6">
    <h2 class="mb-2 text-danger">Disable Link</h2>
    <p class="text-sm text-muted mb-4">
        Disabling stops redirects immediately. Scanners see a generic unavailable page.
        The disable reason and internal note are not shown publicly.
    </p>
    <form method="post" action="/admin/moderation/links/<?= (int) $link['id'] ?>/disable"
          data-confirm="Disable this link? Scans will stop redirecting immediately.">
        <?= CsrfService::field() ?>
        <div class="form-group">
            <label for="disabled_reason">Reason <span class="text-danger">*</span></label>
            <select id="disabled_reason" name="disabled_reason" class="mw-100pct" required>
                <option value="">Choose a reason&hellip;</option>
                <?php foreach (($disableReasons ?? []) as $key => $label): ?>
                <option value="<?= View::e((string) $key) ?>"><?= View::e((string) $label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="moderation_note">Internal note <span class="text-faint fw-normal">(optional)</span></label>
            <textarea id="moderation_note" name="moderation_note" rows="3"
                      placeholder="Private notes for the moderation team"></textarea>
        </div>
        <button type="submit" class="btn btn-danger">Disable Link</button>
    </form>
</div>
<?php endif; ?>

<!-- ── Block destination domain ───────────────────────────────────────────── -->
<?php if ($destinationDomain !== null): ?>
<div class="card-warn mw-520 mb-6">
    <h2 class="mb-2">Block Destination Domain</h2>
    <p class="text-sm text-muted mb-4">
        Adds <code><?= View::e($destinationDomain) ?></code> to the <a href="/admin/moderation/domains">blocked-domain list</a>.
        This <strong>does not</strong> automatically disable this link or any other existing link to the domain &mdash;
        future QR creation and destination edits to the domain will be rejected.
    </p>
    <?php if (!empty($domainAlreadyBlocked)): ?>
    <p class="text-2xs text-warning mb-3">
        This domain is already on the active blocklist. You can manage it from <a href="/admin/moderation/domains">Blocked Domains</a>.
    </p>
    <?php else: ?>
    <form method="post" action="/admin/moderation/links/<?= (int) $link['id'] ?>/block-domain"
          data-confirm="Block the domain <?= View::e($destinationDomain) ?>?">
        <?= CsrfService::field() ?>
        <div class="form-group">
            <label for="block_domain_reason">Reason <span class="text-faint fw-normal">(optional)</span></label>
            <input type="text" id="block_domain_reason" name="reason" maxlength="1000"
                   placeholder="e.g. malware host, repeated abuse" class="mw-100pct">
        </div>
        <button type="submit" class="btn btn-secondary-warn">Block Domain</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Related abuse reports ──────────────────────────────────────────────── -->
<h2 class="mb-3">Related abuse reports</h2>
<?php if (empty($relatedAbuseReports)): ?>
<p class="text-muted-2 text-base mb-6">No abuse reports reference this link.</p>
<?php else: ?>
<div class="scroll-x mb-6">
<table class="text-85">
    <thead>
        <tr>
            <th class="col-65">ID</th>
            <th class="col-140">When (UTC)</th>
            <th class="col-90">Status</th>
            <th class="col-200">Reporter</th>
            <th>Subject</th>
            <th class="col-80"></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($relatedAbuseReports as $r): ?>
        <tr>
            <td>#<?= (int) $r['id'] ?></td>
            <td class="text-muted nowrap"><?= View::e((string) $r['created_at']) ?></td>
            <td>
                <span class="status-<?= View::e((string) $r['status']) ?>">
                    <?= View::e(ucfirst((string) $r['status'])) ?>
                </span>
            </td>
            <td>
                <?= View::e((string) $r['name']) ?>
                <span class="text-2xs text-muted-2 d-block"><?= View::e((string) $r['email']) ?></span>
            </td>
            <td class="word-break"><?= View::e((string) $r['subject']) ?></td>
            <td>
                <a href="/admin/contact-messages/<?= (int) $r['id'] ?>" class="btn btn-secondary btn-xs">View</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<!-- ── Destination history ────────────────────────────────────────────────── -->
<h2 class="mb-3">Destination history</h2>
<?php if (empty($destinationHistory)): ?>
<p class="text-muted-2 text-base mb-6">No destination history recorded.</p>
<?php else: ?>
<div class="scroll-x mb-6">
<table class="text-85">
    <thead>
        <tr>
            <th class="col-140">When (UTC)</th>
            <th class="col-90">Source</th>
            <th>Previous destination</th>
            <th>New destination</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($destinationHistory as $h): ?>
        <tr>
            <td class="text-muted nowrap"><?= View::e((string) $h['created_at']) ?></td>
            <td><?= View::e((string) ($h['change_source'] ?? '')) ?></td>
            <td class="word-break text-faint">
                <?= isset($h['old_target_url']) && $h['old_target_url'] !== null
                    ? View::e((string) $h['old_target_url']) : '—' ?>
            </td>
            <td class="word-break"><?= View::e((string) ($h['new_target_url'] ?? '')) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<!-- ── Recent audit log ───────────────────────────────────────────────────── -->
<h2 class="mb-3">Recent audit log</h2>
<?php if (empty($auditEntries)): ?>
<p class="text-muted-2 text-base">No audit entries for this link or its QR code.</p>
<?php else: ?>
<div class="scroll-x">
<table class="text-85">
    <thead>
        <tr>
            <th class="col-140">When (UTC)</th>
            <th class="col-110">Entity</th>
            <th class="col-180">Action</th>
            <th class="col-220">Actor</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($auditEntries as $a): ?>
        <tr>
            <td class="text-muted nowrap"><?= View::e((string) $a['created_at']) ?></td>
            <td class="text-2xs text-muted-2"><?= View::e((string) $a['entity_type']) ?> #<?= (int) $a['entity_id'] ?></td>
            <td><code class="text-2xs"><?= View::e((string) $a['action']) ?></code></td>
            <td class="text-muted"><?= View::e((string) ($a['actor_email'] ?? '(system)')) ?></td>
            <td class="text-2xs text-muted-2 word-break"><?= View::e((string) ($a['metadata_json'] ?? '')) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
