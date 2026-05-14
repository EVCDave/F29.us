<h1>Login</h1>

<?php if (!empty($errors)): ?>
<ul class="errors">
    <?php foreach ($errors as $e): ?>
    <li><?= View::e($e) ?></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/login">
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
        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            required
        >
    </div>
    <div class="form-group">
        <button type="submit" class="btn">Login</button>
    </div>
</form>

<p><a href="/register">Don't have an account? Register free</a></p>
<p><a href="/forgot-password">Forgot your password?</a></p>
