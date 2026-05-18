<?php $op = static fn(string $k): string => View::e($oldProfile[$k] ?? ''); ?>
<div class="auth-brand">
    <img src="/assets/images/logo.png" alt="F29 QR Code System"
         class="auth-logo" width="110" height="110">
</div>
<h1>Create an Account</h1>

<?php if (!empty($errors)): ?>
<ul class="errors">
    <?php foreach ($errors as $e): ?>
    <li><?= View::e($e) ?></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/register">
    <?= CsrfService::field() ?>
    <div class="form-group">
        <label for="email">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= View::e($oldEmail ?? '') ?>"
            autocomplete="email"
            required
        >
    </div>
    <div class="form-group">
        <label for="password">Password <small class="text-muted-2 fw-normal">(min 8 characters)</small></label>
        <input
            type="password"
            id="password"
            name="password"
            autocomplete="new-password"
            required
        >
    </div>
    <div class="form-group">
        <label for="confirm">Confirm Password</label>
        <input
            type="password"
            id="confirm"
            name="confirm"
            autocomplete="new-password"
            required
        >
    </div>

    <div class="form-grid-2">
        <div class="form-group">
            <label for="first_name">First name <small class="text-muted-2 fw-normal">(optional)</small></label>
            <input type="text" id="first_name" name="first_name"
                   value="<?= $op('first_name') ?>"
                   maxlength="100" autocomplete="given-name">
        </div>
        <div class="form-group">
            <label for="last_name">Last name <small class="text-muted-2 fw-normal">(optional)</small></label>
            <input type="text" id="last_name" name="last_name"
                   value="<?= $op('last_name') ?>"
                   maxlength="100" autocomplete="family-name">
        </div>
    </div>

    <div class="form-group">
        <label for="display_name">Display name <small class="text-muted-2 fw-normal">(optional)</small></label>
        <input type="text" id="display_name" name="display_name"
               value="<?= $op('display_name') ?>"
               maxlength="150" autocomplete="nickname">
    </div>

    <div class="form-grid-2">
        <div class="form-group">
            <label for="company_name">Company <small class="text-muted-2 fw-normal">(optional)</small></label>
            <input type="text" id="company_name" name="company_name"
                   value="<?= $op('company_name') ?>"
                   maxlength="150" autocomplete="organization">
        </div>
        <div class="form-group">
            <label for="phone">Phone <small class="text-muted-2 fw-normal">(optional)</small></label>
            <input type="tel" id="phone" name="phone"
                   value="<?= $op('phone') ?>"
                   maxlength="50" autocomplete="tel">
        </div>
    </div>

    <div class="form-group">
        <label for="timezone">Timezone <small class="text-muted-2 fw-normal">(optional — e.g. America/Chicago)</small></label>
        <input type="text" id="timezone" name="timezone"
               value="<?= $op('timezone') ?>"
               maxlength="100">
    </div>

    <div class="form-group">
        <button type="submit" class="btn">Create Account</button>
    </div>
</form>

<p><a href="/login">Already have an account? Login</a></p>
