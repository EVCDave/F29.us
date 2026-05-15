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

        if (filter_var($_ENV['STRIPE_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN)) {
            foreach (['STRIPE_SECRET_KEY', 'STRIPE_PUBLISHABLE_KEY', 'STRIPE_WEBHOOK_SECRET'] as $var) {
                if (trim($_ENV[$var] ?? '') === '') {
                    $errors[] = "{$var} is required when STRIPE_ENABLED=true";
                }
            }
            $stripeMode = $_ENV['STRIPE_MODE'] ?? '';
            if (!in_array($stripeMode, ['test', 'live'], true)) {
                $errors[] = "STRIPE_MODE must be 'test' or 'live' (got: \"{$stripeMode}\")";
            }
            foreach (['STRIPE_SUCCESS_URL', 'STRIPE_CANCEL_URL'] as $var) {
                $url = trim($_ENV[$var] ?? '');
                if ($url === '') {
                    $errors[] = "{$var} is required when STRIPE_ENABLED=true";
                } elseif (!self::isHttpUrl($url)) {
                    $errors[] = "{$var} must be an http or https URL (got: \"{$url}\")";
                }
            }
        }

        $currency = trim($_ENV['STRIPE_CURRENCY'] ?? '');
        if ($currency !== '' && !preg_match('/^[a-z]{3}$/', $currency)) {
            $errors[] = "STRIPE_CURRENCY must be a lowercase 3-letter ISO 4217 code (got: \"{$currency}\")";
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
