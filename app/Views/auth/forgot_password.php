<h1>Forgot Password</h1>

<?php if ($submitted): ?>

<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:4px;padding:0.9rem 1rem;margin-bottom:1.5rem;color:#166534;font-size:0.92rem">
    If an account exists for that email address, a password reset link has been sent.
    Please check your inbox within 60 minutes.
</div>
<p><a href="/login">&larr; Back to login</a></p>

<?php else: ?>

<?php if ($error !== ''): ?>
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:4px;padding:0.75rem 1rem;margin-bottom:1.25rem;color:#991b1b;font-size:0.9rem">
    <?= View::e($error) ?>
</div>
<?php endif; ?>

<p style="color:#444;max-width:420px">Enter your account email address and we will send you a link to reset your password.</p>

<form method="post" action="/forgot-password" style="max-width:420px">
    <?= CsrfService::field() ?>
    <div class="form-group">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" autocomplete="email" required>
    </div>
    <div class="form-group">
        <button type="submit" class="btn">Send Reset Link</button>
    </div>
</form>

<p style="margin-top:0.5rem"><a href="/login">&larr; Back to login</a></p>

<?php endif; ?>
