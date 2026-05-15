<h1>Forgot Password</h1>

<?php if ($submitted): ?>

<div class="flash flash-success flash-lg">
    If an account exists for that email address, a password reset link has been sent.
    Please check your inbox within 60 minutes.
</div>
<p><a href="/login">&larr; Back to login</a></p>

<?php else: ?>

<?php if ($error !== ''): ?>
<div class="flash flash-error">
    <?= View::e($error) ?>
</div>
<?php endif; ?>

<p class="mw-420">Enter your account email address and we will send you a link to reset your password.</p>

<form method="post" action="/forgot-password" class="mw-420">
    <?= CsrfService::field() ?>
    <div class="form-group">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" autocomplete="email" required>
    </div>
    <div class="form-group">
        <button type="submit" class="btn">Send Reset Link</button>
    </div>
</form>

<p class="mt-2"><a href="/login">&larr; Back to login</a></p>

<?php endif; ?>
