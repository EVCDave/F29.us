<div class="auth-brand">
    <img src="/assets/images/logo.png" alt="F29 QR Code System"
         class="auth-logo" width="110" height="110">
</div>
<h1>Reset Password</h1>

<?php if ($success): ?>

<div class="flash flash-success flash-lg">
    Your password has been reset successfully.
</div>
<p><a href="/login">Sign in with your new password &rarr;</a></p>

<?php elseif (!$valid): ?>

<div class="flash flash-error">
    <?php if ($error !== ''): ?>
        <?= View::e($error) ?>
    <?php else: ?>
        This reset link is invalid, has already been used, or has expired.
    <?php endif; ?>
</div>
<p><a href="/forgot-password">Request a new reset link</a></p>

<?php else: ?>

<?php if ($error !== ''): ?>
<div class="flash flash-error">
    <?= View::e($error) ?>
</div>
<?php endif; ?>

<p class="mw-420">Enter a new password for your account. Minimum 8 characters.</p>

<form method="post" action="/reset-password" class="mw-420">
    <?= CsrfService::field() ?>
    <input type="hidden" name="token" value="<?= View::e($token) ?>">

    <div class="form-group">
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password"
               autocomplete="new-password" minlength="8" required>
    </div>
    <div class="form-group">
        <label for="new_password_confirm">Confirm new password</label>
        <input type="password" id="new_password_confirm" name="new_password_confirm"
               autocomplete="new-password" minlength="8" required>
    </div>
    <div class="form-group">
        <button type="submit" class="btn">Reset Password</button>
    </div>
</form>

<?php endif; ?>
