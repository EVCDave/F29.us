<h1>Admin</h1>
<p class="text-muted-3 mb-6">Internal administration area.</p>

<!-- ── Stats ──────────────────────────────────────────────────────────────── -->
<div class="admin-stat-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-value"><?= (int) $totalUsers ?></div>
        <div class="admin-stat-label">Total Users</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-value"><?= (int) $totalQr ?></div>
        <div class="admin-stat-label">Total QR Codes</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-value"><?= (int) $totalPlans ?></div>
        <div class="admin-stat-label">Plans</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-value"><?= (int) $activeSubs ?></div>
        <div class="admin-stat-label">Active Subscriptions</div>
    </div>
    <div class="admin-stat-card <?= $pendingRequests > 0 ? 'admin-stat-card--warn' : '' ?>">
        <div class="admin-stat-value <?= $pendingRequests > 0 ? 'admin-stat-value--warn' : '' ?>"><?= (int) $pendingRequests ?></div>
        <div class="admin-stat-label">Pending Requests</div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-value"><?= (int) $recentAuditCount ?></div>
        <div class="admin-stat-label">Audit Events (24 h)</div>
    </div>
    <div class="admin-stat-card <?= $failedLogins24h > 20 ? 'admin-stat-card--warn' : '' ?>">
        <div class="admin-stat-value <?= $failedLogins24h > 20 ? 'admin-stat-value--warn' : '' ?>"><?= (int) $failedLogins24h ?></div>
        <div class="admin-stat-label">Failed Logins (24 h)</div>
    </div>
    <div class="admin-stat-card <?= $newContactMessages > 0 ? 'admin-stat-card--warn' : '' ?>">
        <div class="admin-stat-value <?= $newContactMessages > 0 ? 'admin-stat-value--warn' : '' ?>"><?= (int) $newContactMessages ?></div>
        <div class="admin-stat-label">New Contact Messages</div>
    </div>
    <div class="admin-stat-card <?= $newAbuseReports > 0 ? 'admin-stat-card--warn' : '' ?>">
        <div class="admin-stat-value <?= $newAbuseReports > 0 ? 'admin-stat-value--warn' : '' ?>"><?= (int) $newAbuseReports ?></div>
        <div class="admin-stat-label">New Abuse Reports</div>
    </div>
</div>

<!-- ── Tools ──────────────────────────────────────────────────────────────── -->
<h2 class="mb-3">User &amp; Subscription Tools</h2>
<div class="actions-group mb-8">
    <a href="/admin/users" class="btn btn-secondary">User Management</a>
    <a href="/admin/subscriptions" class="btn btn-secondary">Subscription History</a>
    <a href="/admin/subscription-requests" class="btn btn-secondary <?= $pendingRequests > 0 ? 'text-warning btn-secondary-warn' : '' ?>">
        Subscription Requests<?= $pendingRequests > 0 ? ' (' . (int) $pendingRequests . ')' : '' ?>
    </a>
</div>

<h2 class="mb-3">Catalog</h2>
<div class="actions-group mb-8">
    <a href="/admin/plans" class="btn btn-secondary">Plan Catalog</a>
</div>

<h2 class="mb-3">Moderation</h2>
<div class="actions-group mb-8">
    <a href="/admin/moderation/links" class="btn btn-secondary">Moderated Links</a>
    <a href="/admin/moderation/domains" class="btn btn-secondary">Blocked Domains</a>
</div>

<h2 class="mb-3">Support</h2>
<div class="actions-group mb-8">
    <a href="/admin/contact-messages" class="btn btn-secondary <?= $newContactMessages > 0 ? 'text-warning btn-secondary-warn' : '' ?>">
        Contact Messages<?= $newContactMessages > 0 ? ' (' . (int) $newContactMessages . ')' : '' ?>
    </a>
    <a href="/admin/contact-messages?category=abuse&amp;status=new" class="btn btn-secondary <?= $newAbuseReports > 0 ? 'text-warning btn-secondary-warn' : '' ?>">
        Abuse Reports<?= $newAbuseReports > 0 ? ' (' . (int) $newAbuseReports . ')' : '' ?>
    </a>
</div>

<h2 class="mb-3">Visibility &amp; Diagnostics</h2>
<div class="actions-group">
    <a href="/admin/audit-logs" class="btn btn-secondary">Audit Logs</a>
    <a href="/admin/ops" class="btn btn-secondary">Operations</a>
</div>
