<?php
declare(strict_types=1);

class LoginThrottleService
{
    private const MAX_EMAIL_FAILURES  = 5;
    private const MAX_IP_FAILURES     = 20;
    private const WINDOW_SECS         = 900;  // 15 minutes
    private const CLEANUP_RETAIN_DAYS = 90;

    /**
     * True if the email or IP has exceeded the failure threshold in the
     * current window. Both checks are applied; either can trigger lockout.
     */
    public static function isLockedOut(string $email, ?string $ipHash): bool
    {
        $since = gmdate('Y-m-d H:i:s', time() - self::WINDOW_SECS);
        $pdo   = Database::get();

        if ($email !== '') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM login_attempts
                WHERE email_normalized = ?
                  AND success_flag = 0
                  AND attempted_at >= ?
            ");
            $stmt->execute([$email, $since]);
            if ((int) $stmt->fetchColumn() >= self::MAX_EMAIL_FAILURES) {
                return true;
            }
        }

        if ($ipHash !== null) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM login_attempts
                WHERE ip_hash = ?
                  AND success_flag = 0
                  AND attempted_at >= ?
            ");
            $stmt->execute([$ipHash, $since]);
            if ((int) $stmt->fetchColumn() >= self::MAX_IP_FAILURES) {
                return true;
            }
        }

        return false;
    }

    /**
     * Persist one login attempt row. Failures silently swallowed so they
     * never interrupt the auth flow.
     */
    public static function record(string $email, ?string $ipHash, bool $success): void
    {
        try {
            Database::get()->prepare("
                INSERT INTO login_attempts
                    (email_normalized, ip_hash, attempted_at, success_flag)
                VALUES (?, ?, NOW(), ?)
            ")->execute([
                $email !== '' ? $email : null,
                $ipHash,
                $success ? 1 : 0,
            ]);
        } catch (Throwable) {
            // Recording failure must not break the auth flow
        }
    }

    /**
     * Delete login_attempts rows older than $days days.
     * Returns the number of rows removed.
     */
    public static function deleteOlderThan(int $days = self::CLEANUP_RETAIN_DAYS): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        $stmt   = Database::get()->prepare(
            "DELETE FROM login_attempts WHERE attempted_at < ?"
        );
        $stmt->execute([$cutoff]);
        return (int) $stmt->rowCount();
    }

    /**
     * HMAC-SHA256 of the raw IP address using APP_KEY as the secret.
     * Returns null when no IP is available.
     * Throws RuntimeException if APP_KEY is not configured.
     */
    public static function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        $key = $_ENV['APP_KEY'] ?? '';
        if ($key === '') {
            throw new RuntimeException(
                'APP_KEY is not configured. Add it to your .env file before using login throttling.'
            );
        }

        return hash_hmac('sha256', $ip, $key);
    }
}
