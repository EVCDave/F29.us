<h1>Create an Account</h1>
<p class="notice">Placeholder — registration logic not yet implemented.</p>

<form method="post" action="/register">
    <p>
        <label>Email
            <input type="email" name="email" autocomplete="email" required>
        </label>
    </p>
    <p>
        <label>Password
            <input type="password" name="password" autocomplete="new-password" required>
        </label>
    </p>
    <p>
        <button type="submit">Create Account</button>
    </p>
</form>

<p><a href="/login">Already have an account? Login</a></p>
