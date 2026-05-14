<?php
declare(strict_types=1);

class EmailVerificationService
{
    private const TOKEN_TTL_HOURS      = 24;
    private const RESEND_COOLDOWN_SECS = 60;

    // ── Create registration token ─────────────────────────────────────────────

    public static function createRegistrationToken(int $userId, string $email): bool
    {
        $rawToken = bin2hex(random_bytes(32));
        $hash     = hash('sha256', $rawToken);
        $now      = gmdate('Y-m-d H:i:s');
        $expires  = gmdate('Y-m-d H:i:s', strtotime('+' . self::TOKEN_TTL_HOURS . ' hours'));

        $pdo = Database::get();

        // Invalidate any prior unused registration tokens for this user
        $pdo->prepare("
            UPDATE email_verification_tokens
            SET    used_at = ?
            WHERE  user_id = ? AND purpose = 'registration' AND used_at IS NULL
        ")->execute([$now, $userId]);

        $pdo->prepare("
            INSERT INTO email_verification_tokens
                (user_id, email, token_hash, purpose, new_email, expires_at, created_at)
            VALUES (?, ?, ?, 'registration', NULL, ?, ?)
        ")->execute([$userId, $email, $hash, $expires, $now]);

        NotificationService::registrationVerification($email, $rawToken);
        return true;
    }

    // ── Create email-change token ─────────────────────────────────────────────

    public static function createEmailChangeToken(int $userId, string $currentEmail, string $newEmail): bool
    {
        $rawToken = bin2hex(random_bytes(32));
        $hash     = hash('sha256', $rawToken);
        $now      = gmdate('Y-m-d H:i:s');
        $expires  = gmdate('Y-m-d H:i:s', strtotime('+' . self::TOKEN_TTL_HOURS . ' hours'));

        $pdo = Database::get();

        // Invalidate any prior unused email_change tokens for this user
        $pdo->prepare("
            UPDATE email_verification_tokens
            SET    used_at = ?
            WHERE  user_id = ? AND purpose = 'email_change' AND used_at IS NULL
        ")->execute([$now, $userId]);

        $pdo->prepare("
            INSERT INTO email_verification_tokens
                (user_id, email, token_hash, purpose, new_email, expires_at, created_at)
            VALUES (?, ?, ?, 'email_change', ?, ?, ?)
        ")->execute([$userId, $currentEmail, $hash, $newEmail, $expires, $now]);

        NotificationService::emailChangeVerification($newEmail, $rawToken);
        NotificationService::emailChangeSecurityNotice($currentEmail, $newEmail);
        return true;
    }

    // ── Verify token ──────────────────────────────────────────────────────────

    /**
     * @return array{success: bool, message: string, purpose: string|null}
     */
    public static function verifyToken(string $rawToken): array
    {
        if ($rawToken === '') {
            return ['success' => false, 'message' => 'Invalid or missing token.', 'purpose' => null];
        }

        $hash = hash('sha256', $rawToken);
        $pdo  = Database::get();
        $now  = gmdate('Y-m-d H:i:s');

        // BEGIN before SELECT so FOR UPDATE serialises concurrent requests on the same token.
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM email_verification_tokens WHERE token_hash = ? LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([$hash]);
            $token = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$token) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'This verification link is invalid.', 'purpose' => null];
            }

            if ($token['used_at'] !== null) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'This verification link has already been used.', 'purpose' => $token['purpose']];
            }

            if ($token['expires_at'] < $now) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'This verification link has expired. Please request a new one.', 'purpose' => $token['purpose']];
            }

            $userId  = (int) $token['user_id'];
            $purpose = (string) $token['purpose'];

            $pdo->prepare(
                "UPDATE email_verification_tokens SET used_at = ? WHERE id = ?"
            )->execute([$now, $token['id']]);

            if ($purpose === 'registration') {
                $pdo->prepare("
                    UPDATE users
                    SET    email_verified_at = ?, email_verification_required = 0, updated_at = ?
                    WHERE  id = ?
                ")->execute([$now, $now, $userId]);

                AuditLogService::log($userId, 'user', $userId, 'email_verified', [
                    'email'   => $token['email'],
                    'purpose' => 'registration',
                ]);
            } elseif ($purpose === 'email_change') {
                $newEmail = (string) $token['new_email'];

                try {
                    $pdo->prepare("
                        UPDATE users
                        SET    email = ?, email_verified_at = ?, email_verification_required = 0, updated_at = ?
                        WHERE  id = ?
                    ")->execute([$newEmail, $now, $now, $userId]);
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    if ($e->getCode() === '23000') {
                        return ['success' => false, 'message' => 'That email address is already in use by another account.', 'purpose' => $purpose];
                    }
                    throw $e;
                }

                AuditLogService::log($userId, 'user', $userId, 'email_changed', [
                    'old_email' => $token['email'],
                    'new_email' => $newEmail,
                    'via'       => 'email_verification',
                ]);

                NotificationService::emailChangeCompleted((string) $token['email'], $newEmail);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        AuthService::clearCache();

        return ['success' => true, 'message' => '', 'purpose' => $purpose];
    }

    // ── Resend registration verification ─────────────────────────────────────

    /**
     * @return array{ok: bool, reason: string}
     */
    public static function resendRegistrationVerification(int $userId): array
    {
        $pdo = Database::get();

        $stmt = $pdo->prepare(
            "SELECT email, email_verified_at FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['ok' => false, 'reason' => 'User not found.'];
        }

        if ($user['email_verified_at'] !== null) {
            return ['ok' => false, 'reason' => 'Your email is already verified.'];
        }

        // Cooldown: check the most recent token created (used or not)
        $stmt = $pdo->prepare("
            SELECT created_at FROM email_verification_tokens
            WHERE  user_id = ? AND purpose = 'registration'
            ORDER  BY id DESC
            LIMIT  1
        ");
        $stmt->execute([$userId]);
        $last = $stmt->fetchColumn();

        if ($last !== false) {
            $age = time() - strtotime((string) $last);
            if ($age < self::RESEND_COOLDOWN_SECS) {
                $wait = self::RESEND_COOLDOWN_SECS - $age;
                return ['ok' => false, 'reason' => "Please wait {$wait} second(s) before requesting another email."];
            }
        }

        self::createRegistrationToken($userId, (string) $user['email']);

        AuditLogService::log($userId, 'user', $userId, 'verification_email_resent', [
            'email' => $user['email'],
        ]);

        return ['ok' => true, 'reason' => ''];
    }

    // ── Enforce verified email gate ───────────────────────────────────────────

    public static function requireVerifiedEmail(int $userId): void
    {
        $stmt = Database::get()->prepare(
            "SELECT email_verified_at, email_verification_required FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return;
        }

        if ($row['email_verified_at'] === null && (int) $row['email_verification_required'] === 1) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'text' => 'You must verify your email address before performing this action.',
            ];
            redirect('/account/verify-email');
        }
    }
}
