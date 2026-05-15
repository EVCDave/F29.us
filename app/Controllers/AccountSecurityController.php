<?php
declare(strict_types=1);

class AccountSecurityController
{
    public function securityPage(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();
        $pdo    = Database::get();

        $stmt = $pdo->prepare(
            "SELECT id, email, email_verified_at, email_verification_required,
                    password_changed_at, last_login_at
             FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recent security-relevant audit log entries
        $stmt = $pdo->prepare(
            "SELECT action, metadata_json, created_at
             FROM   audit_logs
             WHERE  user_id = ?
               AND  action IN (
                        'password_changed', 'password_reset_requested', 'password_reset_completed',
                        'email_change_requested', 'email_change_completed', 'email_verified'
                    )
             ORDER BY created_at DESC
             LIMIT 15"
        );
        $stmt->execute([$userId]);
        $securityEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent login attempts (success and failure)
        $emailNorm = strtolower(trim($user['email']));
        $stmt = $pdo->prepare(
            "SELECT success_flag, attempted_at
             FROM   login_attempts
             WHERE  email_normalized = ?
             ORDER BY attempted_at DESC
             LIMIT 15"
        );
        $stmt->execute([$emailNorm]);
        $loginAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sessionStartedAt = $_SESSION['session_started_at'] ?? null;

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        View::render('account/security', [
            'pageTitle'       => 'Account Security — f29.us Dynamic QR',
            'user'            => $user,
            'securityEvents'  => $securityEvents,
            'loginAttempts'   => $loginAttempts,
            'sessionStartedAt'=> $sessionStartedAt,
            'flash'           => $flash,
        ]);
    }
}
