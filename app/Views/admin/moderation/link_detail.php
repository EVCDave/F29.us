<div style="display:flex;align-items:baseline;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem">
    <h1>Link #<?= (int) $link['id'] ?> — Moderation</h1>
    <a href="/admin/moderation/links" style="color:#666;font-size:0.9rem">&larr; Moderated Links</a>
</div>

<?php if ($flash): ?>
<div style="
    background: <?= $flash['type'] === 'success' ? '#f0fdf4' : ($flash['type'] === 'error' ? '#fef2f2' : '#eff6ff') ?>;
    border: 1px solid <?= $flash['type'] === 'success' ? '#86efac' : ($flash['type'] === 'error' ? '#fca5a5' : '#93c5fd') ?>;
    color: <?= $flash['type'] === 'success' ? '#166534' : ($flash['type'] === 'error' ? '#991b1b' : '#1e40af') ?>;
    border-radius: 4px; padding: 0.7rem 1rem; margin-bottom: 1.25rem; font-size: 0.9rem;
"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<!-- ── Link info ───────────────────────────────────────────────────────────── -->
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem 1.5rem;margin-bottom:2rem">
    <h2 style="margin-bottom:0.75rem">Link Details</h2>
    <table style="margin:0;font-size:0.88rem">
        <tbody>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1.5rem 0.3rem 0;border:none;width:160px;white-space:nowrap">Short link ID</td>
                <td style="border:none"><?= (int) $link['id'] ?></td>
            </tr>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1.5rem 0.3rem 0;border:none">Slug</td>
                <td style="border:none"><code><?= View::e($link['slug']) ?></code></td>
            </tr>
            <?php if ($link['qr_name'] !== null): ?>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1.5rem 0.3rem 0;border:none">QR name</td>
                <td style="border:none">
                    <?= View::e($link['qr_name']) ?>
                    <?php if ($link['qr_id']): ?>
                    <a href="/qr/<?= (int) $link['qr_id'] ?>" style="font-size:0.8rem;margin-left:0.5rem"
                       title="User QR detail page">&nearr; QR detail</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1.5rem 0.3rem 0;border:none">Owner</td>
                <td style="border:none">
                    <?php if ($link['owner_id']): ?>
                    <a href="/admin/users/<?= (int) $link['owner_id'] ?>"><?= View::e($link['owner_email'] ?? '—') ?></a>
                    <?php else: ?>
                    <?= View::e($link['owner_email'] ?? '—') ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1.5rem 0.3rem 0;border:none">Current destination</td>
                <td style="border:none;word-break:break-all">
                    <a href="<?= View::e($link['current_target_url']) ?>" target="_blank">
                        <?= View::e($link['current_target_url']) ?>
                    </a>
                </td>
            </tr>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1.5rem 0.3rem 0;border:none">Status</td>
                <td style="border:none">
                    <span class="status-<?= View::e($link['status']) ?>"><?= View::e(ucfirst($link['status'])) ?></span>
                </td>
            </tr>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1.5rem 0.3rem 0;border:none">Created at</td>
                <td style="border:none"><?= View::e($link['created_at']) ?> UTC</td>
            </tr>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1.5rem 0.3rem 0;border:none">Scans (last 30 d)</td>
                <td style="border:none"><?= (int) $recentScans ?></td>
            </tr>
        </tbody>
    </table>

    <?php if ($link['qr_id']): ?>
    <div style="margin-top:0.75rem">
        <a href="/qr/<?= (int) $link['qr_id'] ?>/analytics" class="btn btn-secondary" style="font-size:0.82rem">View Analytics</a>
    </div>
    <?php endif; ?>
</div>

<!-- ── Moderation metadata ────────────────────────────────────────────────── -->
<?php if ($link['status'] === 'disabled'): ?>
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:1.25rem 1.5rem;margin-bottom:2rem">
    <h2 style="margin-bottom:0.75rem;color:#991b1b">Disabled</h2>
    <table style="margin:0;font-size:0.88rem">
        <tbody>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1.5rem 0.3rem 0;border:none;width:160px">Disabled reason</td>
                <td style="border:none;color:#991b1b;font-weight:500"><?= View::e($link['disabled_reason'] ?? '—') ?></td>
            </tr>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1.5rem 0.3rem 0;border:none">Disabled at</td>
                <td style="border:none"><?= View::e($link['disabled_at'] ?? '—') ?> UTC</td>
            </tr>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1.5rem 0.3rem 0;border:none">Disabled by</td>
                <td style="border:none"><?= View::e($link['disabled_by_email'] ?? '—') ?></td>
            </tr>
            <?php if ($link['moderation_note']): ?>
            <tr>
                <td style="color:#6b7280;padding:0.3rem 1.5rem 0.3rem 0;border:none">Internal note</td>
                <td style="border:none"><?= View::e($link['moderation_note']) ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ── Actions ─────────────────────────────────────────────────────────────── -->
<?php if ($link['status'] === 'disabled'): ?>
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem 1.5rem;max-width:520px">
    <h2 style="margin-bottom:0.5rem">Restore Link</h2>
    <p style="font-size:0.85rem;color:#6b7280;margin-bottom:1rem">
        Restoring sets status back to <strong>active</strong>. Moderation metadata is preserved for audit context.
    </p>
    <form method="post" action="/admin/moderation/links/<?= (int) $link['id'] ?>/restore"
          onsubmit="return confirm('Restore this link to active?')">
        <?= CsrfService::field() ?>
        <button type="submit" class="btn">Restore to Active</button>
    </form>
</div>

<?php else: ?>
<div style="background:#fff;border:1px solid #fca5a5;border-radius:6px;padding:1.25rem 1.5rem;max-width:520px">
    <h2 style="margin-bottom:0.5rem;color:#991b1b">Disable Link</h2>
    <p style="font-size:0.85rem;color:#6b7280;margin-bottom:1rem">
        Disabling stops redirects immediately. The owner will see a generic unavailable message.
        The disable reason and internal note are not shown publicly.
    </p>
    <form method="post" action="/admin/moderation/links/<?= (int) $link['id'] ?>/disable">
        <?= CsrfService::field() ?>
        <div class="form-group">
            <label for="disabled_reason">Reason <span style="color:#991b1b">*</span></label>
            <input type="text" id="disabled_reason" name="disabled_reason"
                   placeholder="e.g. Phishing, malware, ToS violation"
                   maxlength="255" required
                   style="max-width:100%">
        </div>
        <div class="form-group">
            <label for="moderation_note">Internal note <span style="color:#9ca3af;font-weight:400">(optional)</span></label>
            <textarea id="moderation_note" name="moderation_note" rows="3"
                      style="display:block;width:100%;max-width:100%;padding:0.4rem 0.65rem;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;font-family:inherit"
                      placeholder="Private notes for the moderation team"></textarea>
        </div>
        <button type="submit" class="btn btn-danger"
                onclick="return confirm('Disable this link? Scans will stop redirecting immediately.')">
            Disable Link
        </button>
    </form>
</div>
<?php endif; ?>
