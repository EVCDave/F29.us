<?php
declare(strict_types=1);

class ConfigValidator
{
    private const KNOWN_ENVS = ['local', 'staging', 'production'];

    public static function validate(): void
    {
        $errors = self::check();
        if (empty($errors)) {
            return;
        }

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Configuration error(s):\n");
            foreach ($errors as $msg) {
                fwrite(STDERR, "  - {$msg}\n");
            }
            exit(1);
        }

        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo self::fallbackHtml();
        exit;
    }

    private static function check(): array
    {
        $errors = [];

        $env = $_ENV['APP_ENV'] ?? 'production';
        if (!in_array($env, self::KNOWN_ENVS, true)) {
            $errors[] = 'APP_ENV must be one of: ' . implode(', ', self::KNOWN_ENVS) . " (got: \"{$env}\")";
        }

        $appUrl = $_ENV['APP_URL'] ?? '';
        if ($appUrl === '') {
            $errors[] = 'APP_URL is required';
        } elseif (!self::isHttpUrl($appUrl)) {
            $errors[] = "APP_URL must be an http or https URL (got: \"{$appUrl}\")";
        }

        $key = $_ENV['APP_KEY'] ?? '';
        if ($key === '') {
            $errors[] = 'APP_KEY is required — generate with: php -r "echo bin2hex(random_bytes(32));"';
        } elseif (strlen($key) < 32) {
            $errors[] = 'APP_KEY must be at least 32 characters';
        }

        $qrUrl = $_ENV['QR_BASE_URL'] ?? '';
        if ($qrUrl === '') {
            $errors[] = 'QR_BASE_URL is required';
        } elseif (!self::isHttpUrl($qrUrl)) {
            $errors[] = "QR_BASE_URL must be an http or https URL (got: \"{$qrUrl}\")";
        }

        foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $var) {
            if (trim($_ENV[$var] ?? '') === '') {
                $errors[] = "{$var} is required";
            }
        }

        return $errors;
    }

    private static function isHttpUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        return in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    private static function fallbackHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Server Error</title>
<style>body{font-family:sans-serif;text-align:center;padding:4rem 1rem;color:#333}
h1{font-size:2rem;margin-bottom:1rem}</style>
</head>
<body>
<h1>Server Error</h1>
<p>The application is not configured correctly. Please contact the administrator.</p>
</body>
</html>
HTML;
    }
}
