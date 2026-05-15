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
            "SELECT id, email, status, role, created_at, last_login_at,
                    first_name, last_name, display_name, company_name, phone, timezone,
                    email_verified_at, email_verification_required
             FROM users WHERE id = ? LIMIT 1"
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

    // ── Update profile ────────────────────────────────────────────────────────

    public function updateProfile(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();

        $userId = (int) AuthService::userId();
        $pdo    = Database::get();

        $raw = [
            'first_name'   => trim($_POST['first_name']   ?? ''),
            'last_name'    => trim($_POST['last_name']    ?? ''),
            'display_name' => trim($_POST['display_name'] ?? ''),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'phone'        => trim($_POST['phone']        ?? ''),
            'timezone'     => trim($_POST['timezone']     ?? ''),
        ];

        $errors = [];
        if (mb_strlen($raw['first_name'])   > 100) $errors[] = 'First name must be 100 characters or fewer.';
        if (mb_strlen($raw['last_name'])    > 100) $errors[] = 'Last name must be 100 characters or fewer.';
        if (mb_strlen($raw['display_name']) > 150) $errors[] = 'Display name must be 150 characters or fewer.';
        if (mb_strlen($raw['company_name']) > 150) $errors[] = 'Company must be 150 characters or fewer.';
        if (mb_strlen($raw['phone'])        >  50) $errors[] = 'Phone must be 50 characters or fewer.';
        if (mb_strlen($raw['timezone'])     > 100) $errors[] = 'Timezone must be 100 characters or fewer.';

        if (!empty($errors)) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => implode(' ', $errors), 'profile' => $raw];
            redirect('/account/settings');
        }

        // Empty string → NULL
        $vals = array_map(fn($v) => $v !== '' ? $v : null, $raw);

        // Load current values to detect what actually changed
        $stmt = $pdo->prepare(
            "SELECT first_name, last_name, display_name, company_name, phone, timezone
             FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        $changedFields = [];
        foreach ($vals as $key => $value) {
            if (($current[$key] ?? null) !== $value) {
                $changedFields[] = $key;
            }
        }

        if (empty($changedFields)) {
            $_SESSION['flash'] = ['type' => 'info', 'text' => 'No changes were made.'];
            redirect('/account/settings');
        }

        $now = gmdate('Y-m-d H:i:s');
        $pdo->prepare(
            "UPDATE users
             SET first_name = ?, last_name = ?, display_name = ?,
                 company_name = ?, phone = ?, timezone = ?, updated_at = ?
             WHERE id = ?"
        )->execute([
            $vals['first_name'], $vals['last_name'], $vals['display_name'],
            $vals['company_name'], $vals['phone'], $vals['timezone'],
            $now, $userId,
        ]);

        AuthService::clearCache();

        AuditLogService::log($userId, 'user', $userId, 'profile_updated', [
            'changed_fields' => $changedFields,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Your profile has been updated.'];
        redirect('/account/settings');
    }

    // ── Update email ──────────────────────────────────────────────────────────

    public function updateEmail(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();

        $userId   = (int) AuthService::userId();

        EmailVerificationService::requireVerifiedEmail($userId);

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

        // Pre-check uniqueness so the user gets a clear error before a token is sent
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        $stmt->execute([$newEmail, $userId]);
        if ($stmt->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'That email address is already in use.', 'email' => $newEmail];
            redirect('/account/settings');
        }

        $currentEmail = (string) $user['email'];

        AuditLogService::log($userId, 'user', $userId, 'email_change_requested', [
            'old_email' => $currentEmail,
            'new_email' => $newEmail,
        ]);

        EmailVerificationService::createEmailChangeToken($userId, $currentEmail, $newEmail);

        $_SESSION['flash'] = [
            'type' => 'success',
            'text' => 'A confirmation link has been sent to ' . $newEmail . '. Your email address will update once you click the link.',
        ];
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
            "UPDATE users SET password_hash = ?, password_changed_at = ?, updated_at = ? WHERE id = ?"
        )->execute([$hash, $now, $now, $userId]);

        session_regenerate_id(true);
        AuthService::clearCache();

        AuditLogService::log($userId, 'user', $userId, 'password_changed', [
            'password_changed' => true,
        ]);

        NotificationService::accountPasswordChanged($userId);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Your password has been updated.'];
        redirect('/account/settings');
    }
}
