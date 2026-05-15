<?php
declare(strict_types=1);

class PasswordResetService
{
    private const TOKEN_TTL_MINUTES = 60;

    // ── Request reset ─────────────────────────────────────────────────────────

    public static function requestReset(string $email): void
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return;
        }

        $pdo = Database::get();

        $stmt = $pdo->prepare(
            "SELECT id, status FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Silently do nothing — never reveal whether the email exists
        if (!$user || $user['status'] === 'suspended') {
            return;
        }

        $userId = (int) $user['id'];
        $now    = gmdate('Y-m-d H:i:s');

        // Invalidate any prior unused reset tokens for this user
        $pdo->prepare("
            UPDATE password_reset_tokens
            SET    used_at = ?
            WHERE  user_id = ? AND used_at IS NULL
        ")->execute([$now, $userId]);

        $rawToken = bin2hex(random_bytes(32));
        $expires  = gmdate('Y-m-d H:i:s', strtotime('+' . self::TOKEN_TTL_MINUTES . ' minutes'));

        $pdo->prepare("
            INSERT INTO password_reset_tokens
                (user_id, email, token_hash, expires_at, created_at)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$userId, $email, hash('sha256', $rawToken), $expires, $now]);

        AuditLogService::log($userId, 'user', $userId, 'password_reset_requested', [
            'email' => $email,
        ]);

        NotificationService::passwordResetRequested($userId, $rawToken);
    }

    // ── Validate token for form display ───────────────────────────────────────

    public static function getValidTokenContext(string $rawToken): ?array
    {
        if ($rawToken === '') {
            return null;
        }

        $now  = gmdate('Y-m-d H:i:s');
        $stmt = Database::get()->prepare(
            "SELECT id, user_id, email, used_at, expires_at
             FROM   password_reset_tokens
             WHERE  token_hash = ?
             LIMIT  1"
        );
        $stmt->execute([hash('sha256', $rawToken)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['used_at'] !== null || $row['expires_at'] < $now) {
            return null;
        }

        return $row;
    }

    // ── Apply password reset ──────────────────────────────────────────────────

    /**
     * @return array{ok: bool, message: string}
     */
    public static function resetPassword(string $rawToken, string $newPassword, string $confirm): array
    {
        if ($rawToken === '') {
            return ['ok' => false, 'message' => 'Invalid or missing reset token.'];
        }

        $errors = self::validatePassword($newPassword, $confirm);
        if (!empty($errors)) {
            return ['ok' => false, 'message' => implode(' ', $errors)];
        }

        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM password_reset_tokens WHERE token_hash = ? LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([hash('sha256', $rawToken)]);
            $token = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$token) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'This reset link is invalid.'];
            }

            if ($token['used_at'] !== null) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'This reset link has already been used.'];
            }

            if ($token['expires_at'] < $now) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'This reset link has expired. Please request a new one.'];
            }

            $userId = (int) $token['user_id'];

            // Mark the used token, then sweep any remaining unused tokens for this user
            $pdo->prepare(
                "UPDATE password_reset_tokens SET used_at = ? WHERE id = ?"
            )->execute([$now, $token['id']]);

            $pdo->prepare(
                "UPDATE password_reset_tokens SET used_at = ? WHERE user_id = ? AND used_at IS NULL"
            )->execute([$now, $userId]);

            $pdo->prepare(
                "UPDATE users SET password_hash = ?, password_changed_at = ?, updated_at = ? WHERE id = ?"
            )->execute([password_hash($newPassword, PASSWORD_BCRYPT), $now, $now, $userId]);

            AuditLogService::log($userId, 'user', $userId, 'password_reset_completed', [
                'email' => $token['email'],
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // If this browser is currently logged in as the same user, rotate the session
        if (AuthService::userId() === $userId) {
            session_regenerate_id(true);
            AuthService::clearCache();
        }

        NotificationService::passwordResetCompleted($userId);

        return ['ok' => true, 'message' => ''];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** @return string[] */
    private static function validatePassword(string $password, string $confirm): array
    {
        $errors = [];

        if ($password === '') {
            $errors[] = 'New password is required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if ($confirm === '') {
            $errors[] = 'Please confirm your new password.';
        } elseif ($password !== '' && $password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        return $errors;
    }
}
