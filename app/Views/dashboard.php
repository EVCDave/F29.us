<div class="page-header mb-6">
    <h1 class="mb-0">Dashboard</h1>
    <span class="text-faint text-88"><?= View::e(UserService::displayName($user)) ?></span>
</div>

<?php if ($billingBanner): ?>
<?php $bannerCls = $billingBanner['type'] === 'warning' ? 'flash-error' : 'flash-info'; ?>
<div class="flash <?= $bannerCls ?> mb-6">
    <?= View::e($billingBanner['message']) ?>
    <a href="/account/subscription" class="ml-2" style="font-size:inherit">View subscription</a>
</div>
<?php endif; ?>

<!-- ── QR code summary ────────────────────────────────────────────────────── -->
<div class="stat-grid">

    <a href="/qr" class="stat-card-link">
    <div class="stat-card">
        <div class="stat-value"><?= $counts['total'] ?></div>
        <div class="stat-label">Total QR codes</div>
    </div>
    </a>

    <a href="/qr?status=active" class="stat-card-link">
    <div class="stat-card">
        <div class="stat-value stat-value-active"><?= $counts['active'] ?></div>
        <div class="stat-label">Active</div>
    </div>
    </a>

    <a href="/qr?status=paused" class="stat-card-link">
    <div class="stat-card">
        <div class="stat-value stat-value-paused"><?= $counts['paused'] ?></div>
        <div class="stat-label">Paused</div>
    </div>
    </a>

    <a href="/qr?status=archived" class="stat-card-link">
    <div class="stat-card">
        <div class="stat-value stat-value-archive"><?= $counts['archived'] ?></div>
        <div class="stat-label">Archived</div>
    </div>
    </a>

</div>

<?php if ($maxQr > 0): ?>
<p class="text-sm text-muted-2 mb-4">
    Active QR usage: <?= $countableQr ?> of <?= $maxQr ?>
    <?php if ($countableQr >= $maxQr): ?>
    &mdash; <span class="text-danger">limit reached</span>
    <?php endif; ?>
</p>
<?php endif; ?>

<?php if ($counts['disabled'] > 0): ?>
<p class="text-sm text-danger mb-6">
    <?= $counts['disabled'] ?> QR code<?= $counts['disabled'] !== 1 ? 's' : '' ?> disabled by an administrator.
    <a href="/qr?status=disabled" class="text-danger">View</a>
</p>
<?php endif; ?>

<!-- ── Quick actions ──────────────────────────────────────────────────────── -->
<h2 class="mb-3">Quick actions</h2>

<div class="actions-group mb-8">
    <a href="/qr/create" class="btn">Create QR Code</a>
    <a href="/qr" class="btn btn-secondary">My QR Codes</a>
    <a href="/account/subscription" class="btn btn-secondary">Subscription</a>
    <a href="/pricing" class="btn btn-secondary">Pricing</a>
    <a href="/account/settings" class="btn btn-secondary">Account Settings</a>
</div>
