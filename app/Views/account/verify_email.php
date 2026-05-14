<h1>Verify Your Email</h1>

<?php if ($flash): ?>
<div style="
    background: <?= $flash['type'] === 'success' ? '#f0fdf4' : ($flash['type'] === 'error' ? '#fef2f2' : '#eff6ff') ?>;
    border: 1px solid <?= $flash['type'] === 'success' ? '#86efac' : ($flash['type'] === 'error' ? '#fca5a5' : '#93c5fd') ?>;
    color: <?= $flash['type'] === 'success' ? '#166534' : ($flash['type'] === 'error' ? '#991b1b' : '#1e40af') ?>;
    border-radius: 4px;
    padding: 0.7rem 1rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
"><?= View::e($flash['text']) ?></div>
<?php endif; ?>

<?php if (!empty($user['email_verified_at'])): ?>
<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:1.25rem 1.5rem;max-width:480px">
    <p style="color:#166534;margin:0;font-weight:500">Your email address is verified.</p>
</div>
<p style="margin-top:1.25rem"><a href="/dashboard">&larr; Back to Dashboard</a></p>

<?php else: ?>
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:1.25rem 1.5rem;max-width:480px;margin-bottom:2rem">
    <p style="color:#444;margin-bottom:0.75rem">
        A verification email was sent to <strong><?= View::e($user['email']) ?></strong> when you registered.
        Please check your inbox (and spam folder) and click the link to verify your email address.
    </p>
    <p style="color:#6b7280;font-size:0.88rem;margin-bottom:1.25rem">
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
