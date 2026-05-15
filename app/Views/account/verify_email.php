<h1>Verify Your Email</h1>

<?php if ($flash): ?>
<div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<?php if (!empty($user['email_verified_at'])): ?>
<div class="card-success mw-480">
    <p class="text-success fw-medium mb-0">Your email address is verified.</p>
</div>
<p class="mt-5"><a href="/dashboard">&larr; Back to Dashboard</a></p>

<?php else: ?>
<div class="card mw-480">
    <p class="mb-3">
        A verification email was sent to <strong><?= View::e($user['email']) ?></strong> when you registered.
        Please check your inbox (and spam folder) and click the link to verify your email address.
    </p>
    <p class="text-88 text-muted mb-5">
        Verification is required to create QR codes and request plan changes.
        You can continue browsing your account in the meantime.
    </p>

    <form method="post" action="/account/verify-email/resend">
        <?= CsrfService::field() ?>
        <button type="submit" class="btn">Resend Verification Email</button>
    </form>
</div>

<p><a href="/dashboard">&larr; Back to Dashboard</a></p>
<?php endif; ?>
