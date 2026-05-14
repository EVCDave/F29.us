<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:0.5rem">
    <h1 style="margin-bottom:0">Dashboard</h1>
    <span style="color:#9ca3af;font-size:0.88rem"><?= View::e($user['email'] ?? '') ?></span>
</div>

<!-- ── QR code summary ────────────────────────────────────────────────────── -->
<div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:2rem">

    <a href="/qr" style="text-decoration:none;flex:1;min-width:110px;max-width:160px">
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1rem 1.25rem;text-align:center">
        <div style="font-size:1.9rem;font-weight:700;color:#1a1a2e"><?= $counts['total'] ?></div>
        <div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem">Total QR codes</div>
    </div>
    </a>

    <a href="/qr?status=active" style="text-decoration:none;flex:1;min-width:110px;max-width:160px">
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1rem 1.25rem;text-align:center">
        <div style="font-size:1.9rem;font-weight:700;color:#166534"><?= $counts['active'] ?></div>
        <div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem">Active</div>
    </div>
    </a>

    <a href="/qr?status=paused" style="text-decoration:none;flex:1;min-width:110px;max-width:160px">
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1rem 1.25rem;text-align:center">
        <div style="font-size:1.9rem;font-weight:700;color:#92400e"><?= $counts['paused'] ?></div>
        <div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem">Paused</div>
    </div>
    </a>

    <a href="/qr?status=archived" style="text-decoration:none;flex:1;min-width:110px;max-width:160px">
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1rem 1.25rem;text-align:center">
        <div style="font-size:1.9rem;font-weight:700;color:#6b7280"><?= $counts['archived'] ?></div>
        <div style="font-size:0.8rem;color:#6b7280;margin-top:0.15rem">Archived</div>
    </div>
    </a>

</div>

<?php if ($counts['disabled'] > 0): ?>
<p style="font-size:0.85rem;color:#991b1b;margin-bottom:1.5rem">
    <?= $counts['disabled'] ?> QR code<?= $counts['disabled'] !== 1 ? 's' : '' ?> disabled by an administrator.
    <a href="/qr?status=disabled" style="color:#991b1b">View</a>
</p>
<?php endif; ?>

<!-- ── Quick actions ──────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.75rem">Quick actions</h2>

<div class="actions-group" style="margin-bottom:2rem">
    <a href="/qr/create" class="btn">Create QR Code</a>
    <a href="/qr" class="btn btn-secondary">My QR Codes</a>
    <a href="/account/subscription" class="btn btn-secondary">Subscription</a>
    <a href="/pricing" class="btn btn-secondary">Pricing</a>
    <a href="/account/settings" class="btn btn-secondary">Account Settings</a>
</div>
