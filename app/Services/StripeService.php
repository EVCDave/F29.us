<?php
declare(strict_types=1);

class StripeService
{
    public static function isEnabled(): bool
    {
        return filter_var($_ENV['STRIPE_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

    public static function mode(): string
    {
        return $_ENV['STRIPE_MODE'] ?? 'test';
    }

    public static function currency(): string
    {
        return strtolower(trim($_ENV['STRIPE_CURRENCY'] ?? 'usd'));
    }

    public static function clientReady(): bool
    {
        return class_exists('\Stripe\StripeClient');
    }

    public static function requireEnabled(): void
    {
        if (!self::isEnabled()) {
            throw new RuntimeException('Stripe is not enabled.');
        }
    }
}
