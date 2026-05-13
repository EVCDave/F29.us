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
        <label for="password">Password <small style="color:#888;font-weight:400">(min 8 characters)</small></label>
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
    <div class="form-group">
        <button type="submit" class="btn">Create Account</button>
    </div>
</form>

<p><a href="/login">Already have an account? Login</a></p>
