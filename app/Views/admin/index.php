<h1>Admin</h1>
<p style="color:#666;margin-bottom:1.5rem">Internal administration area.</p>

<!-- ── Stats ──────────────────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;max-width:860px;margin-bottom:2.5rem">
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem">
        <div style="font-size:1.75rem;font-weight:700"><?= (int) $totalUsers ?></div>
        <div style="color:#666;font-size:0.85rem;margin-top:0.2rem">Total Users</div>
    </div>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem">
        <div style="font-size:1.75rem;font-weight:700"><?= (int) $totalQr ?></div>
        <div style="color:#666;font-size:0.85rem;margin-top:0.2rem">Total QR Codes</div>
    </div>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem">
        <div style="font-size:1.75rem;font-weight:700"><?= (int) $totalPlans ?></div>
        <div style="color:#666;font-size:0.85rem;margin-top:0.2rem">Plans</div>
    </div>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem">
        <div style="font-size:1.75rem;font-weight:700"><?= (int) $activeSubs ?></div>
        <div style="color:#666;font-size:0.85rem;margin-top:0.2rem">Active Subscriptions</div>
    </div>
    <div style="background:<?= $pendingRequests > 0 ? '#fef3c7' : '#fff' ?>;border:1px solid <?= $pendingRequests > 0 ? '#f59e0b' : '#e5e7eb' ?>;border-radius:6px;padding:1.25rem">
        <div style="font-size:1.75rem;font-weight:700;<?= $pendingRequests > 0 ? 'color:#92400e' : '' ?>"><?= (int) $pendingRequests ?></div>
        <div style="color:#666;font-size:0.85rem;margin-top:0.2rem">Pending Requests</div>
    </div>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem">
        <div style="font-size:1.75rem;font-weight:700"><?= (int) $recentAuditCount ?></div>
        <div style="color:#666;font-size:0.85rem;margin-top:0.2rem">Audit Events (24 h)</div>
    </div>
    <div style="background:<?= $failedLogins24h > 20 ? '#fef3c7' : '#fff' ?>;border:1px solid <?= $failedLogins24h > 20 ? '#f59e0b' : '#e5e7eb' ?>;border-radius:6px;padding:1.25rem">
        <div style="font-size:1.75rem;font-weight:700;<?= $failedLogins24h > 20 ? 'color:#92400e' : '' ?>"><?= (int) $failedLogins24h ?></div>
        <div style="color:#666;font-size:0.85rem;margin-top:0.2rem">Failed Logins (24 h)</div>
    </div>
</div>

<!-- ── Tools ──────────────────────────────────────────────────────────────── -->
<h2 style="margin-bottom:0.75rem">User &amp; Subscription Tools</h2>
<div class="actions-group" style="margin-bottom:2rem">
    <a href="/admin/users" class="btn btn-secondary">User Management</a>
    <a href="/admin/subscriptions" class="btn btn-secondary">Subscription History</a>
    <a href="/admin/subscription-requests" class="btn btn-secondary"
       <?= $pendingRequests > 0 ? 'style="border-color:#f59e0b;color:#92400e"' : '' ?>>
        Subscription Requests<?= $pendingRequests > 0 ? ' (' . (int) $pendingRequests . ')' : '' ?>
    </a>
</div>

<h2 style="margin-bottom:0.75rem">Catalog</h2>
<div class="actions-group" style="margin-bottom:2rem">
    <a href="/admin/plans" class="btn btn-secondary">Plan Catalog</a>
</div>

<h2 style="margin-bottom:0.75rem">Moderation</h2>
<div class="actions-group" style="margin-bottom:2rem">
    <a href="/admin/moderation/links" class="btn btn-secondary">Moderated Links</a>
    <a href="/admin/moderation/domains" class="btn btn-secondary">Blocked Domains</a>
</div>

<h2 style="margin-bottom:0.75rem">Visibility &amp; Diagnostics</h2>
<div class="actions-group">
    <a href="/admin/audit-logs" class="btn btn-secondary">Audit Logs</a>
    <a href="/admin/ops" class="btn btn-secondary">Operations</a>
</div>
