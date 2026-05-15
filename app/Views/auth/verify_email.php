<div class="verify-card">

<?php if ($success): ?>
    <div class="verify-icon">&#10003;</div>
    <h1 class="verify-h1">Email verified</h1>
    <?php if ($purpose === 'email_change'): ?>
        <p class="verify-p">Your email address has been updated successfully.</p>
    <?php else: ?>
        <p class="verify-p">Your email address has been verified. Thank you!</p>
    <?php endif; ?>
    <a href="/dashboard" class="btn">Go to Dashboard</a>

<?php else: ?>
    <div class="verify-icon verify-icon--fail">&#10007;</div>
    <h1 class="verify-h1">Verification failed</h1>
    <p class="verify-p"><?= View::e($message) ?></p>

    <?php if (AuthService::isLoggedIn()): ?>
        <a href="/account/verify-email" class="btn">Request new link</a>
    <?php else: ?>
        <a href="/login" class="btn">Sign in</a>
    <?php endif; ?>
<?php endif; ?>

</div>
