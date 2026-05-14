<?php $navUser = AuthService::currentUser(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($pageTitle ?? 'f29.us Dynamic QR') ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f7; color: #1d1d1f; line-height: 1.6; }
        a { color: #0066cc; }
        a:hover { text-decoration: none; }
        .container { max-width: 960px; margin: 0 auto; padding: 0 1.5rem; }

        /* Nav */
        .nav { background: #1a1a2e; padding: 0.8rem 0; }
        .nav .container { display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap; }
        .nav-brand { font-weight: 700; font-size: 1.05rem; color: #fff; text-decoration: none; margin-right: 0.25rem; }
        .nav-link { color: #bbb; text-decoration: none; font-size: 0.9rem; }
        .nav-link:hover { color: #fff; }
        .nav-spacer { flex: 1; }
        .nav-user { color: #9ca3af; font-size: 0.82rem; }
        .nav-logout-form { display: inline; }
        .nav-logout-btn {
            background: none;
            border: 1px solid #555;
            color: #bbb;
            padding: 0.2rem 0.6rem;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.82rem;
        }
        .nav-logout-btn:hover { border-color: #999; color: #fff; }

        /* Main content */
        main { padding: 2.5rem 0; }
        h1 { font-size: 1.9rem; font-weight: 700; margin-bottom: 0.6rem; }
        h2 { font-size: 1.35rem; font-weight: 600; margin-bottom: 0.4rem; }
        p  { margin-bottom: 0.9rem; color: #444; }

        /* Notices and alerts */
        .notice {
            display: inline-block;
            background: #fff8e1;
            border: 1px solid #f9a825;
            color: #5d4037;
            padding: 0.45rem 0.9rem;
            border-radius: 4px;
            font-size: 0.82rem;
            margin-bottom: 1.4rem;
        }
        .errors {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #991b1b;
            border-radius: 4px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            list-style: none;
        }
        .errors li + li { margin-top: 0.3rem; }

        /* Forms */
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.3rem; font-size: 0.9rem; font-weight: 500; color: #333; }
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            display: block;
            width: 100%;
            max-width: 380px;
            padding: 0.45rem 0.65rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        input:focus { outline: 2px solid #0066cc; border-color: #0066cc; }
        /* Tables */
        table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: 0.9rem; }
        th, td { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 1px solid #e5e7eb; }
        th { font-weight: 600; background: #f9fafb; color: #374151; }
        tr:hover td { background: #fafafa; }
        td a { color: #0066cc; }

        /* Status badges */
        .status-active   { color: #166534; font-weight: 500; }
        .status-paused   { color: #92400e; font-weight: 500; }
        .status-disabled { color: #991b1b; font-weight: 500; }
        .status-archived { color: #6b7280; font-weight: 500; }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 0.45rem 1.2rem;
            background: #1a1a2e;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 0.88rem;
            cursor: pointer;
            text-decoration: none;
            line-height: 1.5;
        }
        .btn:hover { background: #2e2e50; color: #fff; }
        .btn-secondary {
            background: #fff;
            color: #1a1a2e;
            border: 1px solid #c0c0cc;
        }
        .btn-secondary:hover { background: #f0f0f5; color: #1a1a2e; }
        .btn-danger {
            background: #dc2626;
        }
        .btn-danger:hover { background: #b91c1c; }
        .btn-disabled {
            display: inline-block;
            padding: 0.45rem 1.2rem;
            background: #f3f4f6;
            color: #9ca3af;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 0.88rem;
            cursor: not-allowed;
        }
        .actions-group { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; margin-bottom: 1.5rem; }

        /* Footer */
        footer { border-top: 1px solid #ddd; padding: 1rem 0; margin-top: 2.5rem; }
        footer p { color: #999; font-size: 0.8rem; margin: 0; }
    </style>
</head>
<body>

<nav class="nav">
    <div class="container">
        <a href="/" class="nav-brand">f29.us Dynamic QR</a>
        <a href="/pricing" class="nav-link">Pricing</a>

        <?php if ($navUser): ?>
            <a href="/dashboard" class="nav-link">Dashboard</a>
            <a href="/qr" class="nav-link">My QR Codes</a>
            <a href="/account/settings" class="nav-link">Account</a>
            <a href="/account/subscription" class="nav-link">Subscription</a>
            <?php if ($navUser['role'] === 'admin'): ?>
            <a href="/admin" class="nav-link" style="color:#f9a825">Admin</a>
            <?php endif; ?>
            <div class="nav-spacer"></div>
            <span class="nav-user"><?= View::e($navUser['email']) ?></span>
            <form method="post" action="/logout" class="nav-logout-form">
                <?= CsrfService::field() ?>
                <button type="submit" class="nav-logout-btn">Logout</button>
            </form>
        <?php else: ?>
            <div class="nav-spacer"></div>
            <a href="/login" class="nav-link">Login</a>
            <a href="/register" class="nav-link">Register</a>
        <?php endif; ?>
    </div>
</nav>

<main>
    <div class="container">
        <?= $content ?>
    </div>
</main>

<footer>
    <div class="container">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem">
            <p style="margin:0">&copy; <?= date('Y') ?> f29.us &mdash; Dynamic QR Codes</p>
            <nav style="display:flex;gap:1.25rem;flex-wrap:wrap">
                <a href="/terms"           style="color:#999;font-size:0.8rem;text-decoration:none">Terms</a>
                <a href="/privacy"         style="color:#999;font-size:0.8rem;text-decoration:none">Privacy</a>
                <a href="/acceptable-use"  style="color:#999;font-size:0.8rem;text-decoration:none">Acceptable Use</a>
                <a href="/abuse"           style="color:#999;font-size:0.8rem;text-decoration:none">Report Abuse</a>
                <a href="/contact"         style="color:#999;font-size:0.8rem;text-decoration:none">Contact</a>
            </nav>
        </div>
    </div>
</footer>

</body>
</html>
