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

        .nav { background: #1a1a2e; padding: 0.8rem 0; }
        .nav .container { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
        .nav-brand { font-weight: 700; font-size: 1.05rem; color: #fff; text-decoration: none; margin-right: 0.5rem; }
        .nav-link { color: #bbb; text-decoration: none; font-size: 0.9rem; }
        .nav-link:hover { color: #fff; }
        .nav-spacer { flex: 1; }

        main { padding: 2.5rem 0; }

        h1 { font-size: 1.9rem; font-weight: 700; margin-bottom: 0.6rem; }
        h2 { font-size: 1.35rem; font-weight: 600; margin-bottom: 0.4rem; }
        p  { margin-bottom: 0.9rem; color: #444; }

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
            margin-top: 0.2rem;
        }
        button[type="submit"] {
            display: inline-block;
            padding: 0.5rem 1.4rem;
            background: #1a1a2e;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 0.95rem;
            cursor: pointer;
        }
        button[type="submit"]:hover { background: #2e2e50; }

        footer { border-top: 1px solid #ddd; padding: 1rem 0; margin-top: 2.5rem; }
        footer p { color: #999; font-size: 0.8rem; margin: 0; }
    </style>
</head>
<body>

<nav class="nav">
    <div class="container">
        <a href="/" class="nav-brand">f29.us Dynamic QR</a>
        <a href="/dashboard" class="nav-link">Dashboard</a>
        <a href="/qr" class="nav-link">My QR Codes</a>
        <div class="nav-spacer"></div>
        <a href="/login" class="nav-link">Login</a>
        <a href="/register" class="nav-link">Register</a>
    </div>
</nav>

<main>
    <div class="container">
        <?= $content ?>
    </div>
</main>

<footer>
    <div class="container">
        <p>&copy; <?= date('Y') ?> f29.us &mdash; Dynamic QR Codes</p>
    </div>
</footer>

</body>
</html>
