<h1>Reset Password</h1>

<?php if ($success): ?>

<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:4px;padding:0.9rem 1rem;margin-bottom:1.5rem;color:#166534;font-size:0.92rem">
    Your password has been reset successfully.
</div>
<p><a href="/login">Sign in with your new password &rarr;</a></p>

<?php elseif (!$valid): ?>

<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:4px;padding:0.75rem 1rem;margin-bottom:1.25rem;color:#991b1b;font-size:0.9rem">
    <?php if ($error !== ''): ?>
        <?= View::e($error) ?>
    <?php else: ?>
        This reset link is invalid, has already been used, or has expired.
    <?php endif; ?>
</div>
<p><a href="/forgot-password">Request a new reset link</a></p>

<?php else: ?>

<?php if ($error !== ''): ?>
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:4px;padding:0.75rem 1rem;margin-bottom:1.25rem;color:#991b1b;font-size:0.9rem">
    <?= View::e($error) ?>
</div>
<?php endif; ?>

<p style="color:#444;max-width:420px">Enter a new password for your account. Minimum 8 characters.</p>

<form method="post" action="/reset-password" style="max-width:420px">
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
