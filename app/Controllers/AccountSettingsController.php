<?php
declare(strict_types=1);

class AccountSettingsController
{
    // ── Redirect /account → /account/settings ────────────────────────────────

    public function redirect(array $params = []): void
    {
        AuthService::requireAuth();
        redirect('/account/settings');
    }

    // ── Settings page ─────────────────────────────────────────────────────────

    public function settingsPage(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();

        $stmt = Database::get()->prepare(
            "SELECT id, email, status, role, created_at, last_login_at FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        View::render('account/settings', [
            'pageTitle' => 'Account Settings — f29.us Dynamic QR',
            'user'      => $user,
            'flash'     => $flash,
        ]);
    }

    // ── Update email ──────────────────────────────────────────────────────────

    public function updateEmail(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();

        $userId   = (int) AuthService::userId();
        $pdo      = Database::get();
        $newEmail = strtolower(trim($_POST['new_email'] ?? ''));
        $password = $_POST['current_password'] ?? '';

        if ($newEmail === '') {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'Email address is required.', 'email' => $newEmail];
            redirect('/account/settings');
        }

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'Please enter a valid email address.', 'email' => $newEmail];
            redirect('/account/settings');
        }

        // Load current credentials
        $stmt = $pdo->prepare("SELECT email, password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'Current password is incorrect.', 'email' => $newEmail];
            redirect('/account/settings');
        }

        if ($newEmail === $user['email']) {
            $_SESSION['flash'] = ['type' => 'info', 'text' => 'That is already your email address.'];
            redirect('/account/settings');
        }

        $now = gmdate('Y-m-d H:i:s');
        try {
            $pdo->prepare(
                "UPDATE users SET email = ?, updated_at = ? WHERE id = ?"
            )->execute([$newEmail, $now, $userId]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $_SESSION['flash'] = ['type' => 'error', 'text' => 'That email address is already in use.', 'email' => $newEmail];
                redirect('/account/settings');
            }
            throw $e;
        }

        $oldEmail = $user['email'];
        AuthService::clearCache();

        AuditLogService::log($userId, 'user', $userId, 'email_changed', [
            'old_email' => $oldEmail,
            'new_email' => $newEmail,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Your email address has been updated.'];
        redirect('/account/settings');
    }

    // ── Update password ───────────────────────────────────────────────────────

    public function updatePassword(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();

        $userId      = (int) AuthService::userId();
        $pdo         = Database::get();
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password']     ?? '';
        $confirm     = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($currentPass, $user['password_hash'])) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'Current password is incorrect.'];
            redirect('/account/settings');
        }

        if (strlen($newPass) < 8) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'New password must be at least 8 characters.'];
            redirect('/account/settings');
        }

        if ($newPass !== $confirm) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'New passwords do not match.'];
            redirect('/account/settings');
        }

        if (password_verify($newPass, $user['password_hash'])) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'New password must differ from your current password.'];
            redirect('/account/settings');
        }

        $now  = gmdate('Y-m-d H:i:s');
        $hash = password_hash($newPass, PASSWORD_BCRYPT);

        $pdo->prepare(
            "UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?"
        )->execute([$hash, $now, $userId]);

        session_regenerate_id(true);
        AuthService::clearCache();

        AuditLogService::log($userId, 'user', $userId, 'password_changed', [
            'password_changed' => true,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Your password has been updated.'];
        redirect('/account/settings');
    }
}
