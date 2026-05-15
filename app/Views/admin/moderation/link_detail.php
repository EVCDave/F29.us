<div class="page-header">
    <h1>Link #<?= (int) $link['id'] ?> — Moderation</h1>
    <a href="/admin/moderation/links" class="back-link">&larr; Moderated Links</a>
</div>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<!-- ── Link info ───────────────────────────────────────────────────────────── -->
<div class="card mb-8">
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
                    <?php if ($link['qr_id']): ?>
                    <a href="/qr/<?= (int) $link['qr_id'] ?>" class="text-xs ml-2"
                       title="User QR detail page">&nearr; QR detail</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>Owner</td>
                <td>
                    <?php if ($link['owner_id']): ?>
                    <a href="/admin/users/<?= (int) $link['owner_id'] ?>"><?= View::e($link['owner_email'] ?? '—') ?></a>
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
                </td>
            </tr>
            <tr>
                <td>Status</td>
                <td>
                    <span class="status-<?= View::e($link['status']) ?>"><?= View::e(ucfirst($link['status'])) ?></span>
                </td>
            </tr>
            <tr>
                <td>Created at</td>
                <td><?= View::e($link['created_at']) ?> UTC</td>
            </tr>
            <tr>
                <td>Scans (last 30 d)</td>
                <td><?= (int) $recentScans ?></td>
            </tr>
        </tbody>
    </table>

    <?php if ($link['qr_id']): ?>
    <div class="mt-3">
        <a href="/qr/<?= (int) $link['qr_id'] ?>/analytics" class="btn btn-secondary">View Analytics</a>
    </div>
    <?php endif; ?>
</div>

<!-- ── Moderation metadata ────────────────────────────────────────────────── -->
<?php if ($link['status'] === 'disabled'): ?>
<div class="card-danger mb-8">
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

<!-- ── Actions ─────────────────────────────────────────────────────────────── -->
<?php if ($link['status'] === 'disabled'): ?>
<div class="card mw-520">
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
<div class="card-danger mw-520">
    <h2 class="mb-2 text-danger">Disable Link</h2>
    <p class="text-sm text-muted mb-4">
        Disabling stops redirects immediately. The owner will see a generic unavailable message.
        The disable reason and internal note are not shown publicly.
    </p>
    <form method="post" action="/admin/moderation/links/<?= (int) $link['id'] ?>/disable"
          data-confirm="Disable this link? Scans will stop redirecting immediately.">
        <?= CsrfService::field() ?>
        <div class="form-group">
            <label for="disabled_reason">Reason <span class="text-danger">*</span></label>
            <input type="text" id="disabled_reason" name="disabled_reason"
                   placeholder="e.g. Phishing, malware, ToS violation"
                   maxlength="255" required class="mw-100pct">
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
