<div style="max-width:480px;margin:3rem auto;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:2rem 2.5rem;text-align:center">

<?php if ($success): ?>
    <div style="font-size:2.5rem;margin-bottom:1rem">&#10003;</div>
    <h1 style="font-size:1.5rem;margin-bottom:0.75rem">Email verified</h1>
    <?php if ($purpose === 'email_change'): ?>
        <p style="color:#444;margin-bottom:1.5rem">Your email address has been updated successfully.</p>
    <?php else: ?>
        <p style="color:#444;margin-bottom:1.5rem">Your email address has been verified. Thank you!</p>
    <?php endif; ?>
    <a href="/dashboard" class="btn">Go to Dashboard</a>

<?php else: ?>
    <div style="font-size:2.5rem;margin-bottom:1rem;color:#991b1b">&#10007;</div>
    <h1 style="font-size:1.5rem;margin-bottom:0.75rem">Verification failed</h1>
    <p style="color:#444;margin-bottom:1.5rem"><?= View::e($message) ?></p>

    <?php if (AuthService::isLoggedIn()): ?>
        <a href="/account/verify-email" class="btn">Request new link</a>
    <?php else: ?>
        <a href="/login" class="btn">Sign in</a>
    <?php endif; ?>
<?php endif; ?>

</div>
