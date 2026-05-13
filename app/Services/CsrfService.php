<?php
declare(strict_types=1);

class CsrfService
{
    private const FIELD = '_csrf';
    private const KEY   = 'csrf_token';

    /**
     * Return the current session CSRF token, generating one if needed.
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    /**
     * Return a ready-to-embed hidden input HTML string.
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<input type="hidden" name="' . self::FIELD . '" value="' . $token . '">';
    }

    /**
     * Verify the submitted token against the session token.
     * Uses hash_equals to prevent timing attacks.
     */
    public static function verify(): bool
    {
        $submitted = (string) ($_POST[self::FIELD] ?? '');
        $expected  = (string) ($_SESSION[self::KEY] ?? '');

        if ($submitted === '' || $expected === '') {
            return false;
        }

        return hash_equals($expected, $submitted);
    }

    /**
     * Discard the current token so the next call to token() generates a
     * fresh one. Call after a session-state change (login, register).
     */
    public static function refresh(): void
    {
        unset($_SESSION[self::KEY]);
    }

    /**
     * Halt with 403 if the submitted token is missing or invalid.
     */
    public static function requireValid(): void
    {
        if (self::verify()) {
            return;
        }

        http_response_code(403);
        View::render('errors/forbidden', [
            'pageTitle' => '403 — Request Validation Failed',
            'message'   => 'Invalid or missing security token. Please go back and try again.',
        ]);
        exit;
    }
}
