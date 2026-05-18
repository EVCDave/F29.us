<?php $navUser = AuthService::currentUser(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($pageTitle ?? 'F29 QR Code System') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<nav class="nav">
    <div class="container">
        <a href="/" class="nav-brand" aria-label="F29 QR Code System home">
            <img src="/assets/images/logo.png" alt="" aria-hidden="true"
                 class="brand-logo" width="44" height="44">
            <span class="brand-text">F29 QR Code System</span>
        </a>
        <a href="/pricing" class="nav-link">Pricing</a>

        <?php if ($navUser): ?>
            <a href="/dashboard" class="nav-link">Dashboard</a>
            <a href="/qr" class="nav-link">My QR Codes</a>
            <a href="/account/settings" class="nav-link">Account</a>
            <a href="/account/subscription" class="nav-link">Subscription</a>
            <?php if ($navUser['role'] === 'admin'): ?>
            <a href="/admin" class="nav-link nav-admin">Admin</a>
            <?php endif; ?>
            <div class="nav-spacer"></div>
            <span class="nav-user"><?= View::e(UserService::displayName($navUser)) ?></span>
            <form method="post" action="/logout" class="nav-logout-form">
                <?= CsrfService::field() ?>
                <button type="submit" class="nav-logout-btn">Logout</button>
            </form>
        <?php else: ?>
            <div class="nav-spacer"></div>
            <a href="/login" class="nav-link">Login</a>
            <a href="/register" class="nav-link">Register</a>
        <?php endif; ?>
    </div>
</nav>

<?php
$_unverified = $navUser
    && $navUser['email_verified_at'] === null
    && (int) ($navUser['email_verification_required'] ?? 0) === 1;
?>
<?php if ($_unverified): ?>
<div class="verify-banner">
    <div class="container">
        Please verify your email address to unlock all features.
        <a href="/account/verify-email">Verify now &rarr;</a>
    </div>
</div>
<?php endif; ?>

<main>
    <div class="container">
        <?= $content ?>
    </div>
</main>

<footer>
    <div class="container">
        <div class="footer-inner">
            <p>&copy; <?= date('Y') ?> f29.us &mdash; Dynamic QR Codes</p>
            <nav class="footer-nav">
                <a href="/help">Help</a>
                <a href="/terms">Terms</a>
                <a href="/privacy">Privacy</a>
                <a href="/acceptable-use">Acceptable Use</a>
                <a href="/abuse">Report Abuse</a>
                <a href="/contact">Contact</a>
            </nav>
        </div>
    </div>
</footer>

<script src="/assets/js/app.js" defer></script>
</body>
</html>
