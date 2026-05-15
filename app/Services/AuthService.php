<?php
declare(strict_types=1);

class AuthService
{
    private static ?array $cachedUser = null;

    // ── Session lifecycle ────────────────────────────────────────────────────

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $secure = str_starts_with($_ENV['APP_URL'] ?? '', 'https://');

        session_name('f29_sess');
        session_set_cookie_params([
            'lifetime' => 0,        // expires when browser closes
            'path'     => '/',
            'secure'   => $secure,  // true in production (HTTPS)
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    // ── Current user ─────────────────────────────────────────────────────────

    public static function userId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return is_int($id) ? $id : null;
    }

    public static function isLoggedIn(): bool
    {
        return self::userId() !== null;
    }

    /**
     * Returns a minimal user row (id, email, role, status) or null.
     * Result is cached for the lifetime of the request.
     */
    public static function currentUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $stmt = Database::get()->prepare(
            "SELECT id, email, role, status, first_name, last_name, display_name,
                    email_verified_at, email_verification_required
             FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([self::userId()]);
        self::$cachedUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return self::$cachedUser;
    }

    public static function isAdmin(): bool
    {
        $user = self::currentUser();
        return $user !== null && ($user['role'] ?? '') === 'admin';
    }

    /**
     * Redirect to /login if the request is unauthenticated, or if the
     * session references a user that no longer exists or has been suspended.
     * Uses the per-request cached user row — one DB query at most per request.
     */
    public static function requireAuth(): void
    {
        if (!self::isLoggedIn()) {
            redirect('/login');
        }

        $user = self::currentUser();
        if ($user === null || $user['status'] === 'suspended') {
            self::logout();
            redirect('/login');
        }
    }

    // ── Registration ─────────────────────────────────────────────────────────

    /**
     * Validate, create user + default Free subscription, log the user in.
     * Returns ['ok' => true] or ['ok' => false, 'errors' => string[]].
     */
    public static function register(string $email, string $password, string $confirm): array
    {
        $email = strtolower(trim($email));

        $errors = self::validateRegistration($email, $password, $confirm);
        if (!empty($errors)) {
            return ['ok' => false, 'errors' => $errors];
        }

        $pdo = Database::get();

        // Uniqueness check
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'errors' => ['This email address is already registered.']];
        }

        // Resolve the Free plan — never hard-code a plan ID
        $stmt = $pdo->prepare(
            "SELECT id FROM plans WHERE internal_name = 'free_v1' AND is_active = 1 LIMIT 1"
        );
        $stmt->execute();
        $plan = $stmt->fetch();
        if (!$plan) {
            return ['ok' => false, 'errors' => ['Default plan unavailable. Please contact support.']];
        }
        $planId = (int) $plan['id'];

        // Wrap user creation + subscription in a transaction
        $pdo->beginTransaction();
        try {
            $now  = gmdate('Y-m-d H:i:s');
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $pdo->prepare("
                INSERT INTO users (email, password_hash, status, email_verification_required, created_at, updated_at)
                VALUES (?, ?, 'active', 1, ?, ?)
            ")->execute([$email, $hash, $now, $now]);

            $userId = (int) $pdo->lastInsertId();

            $pdo->prepare("
                INSERT INTO user_subscriptions
                    (user_id, plan_id, status, billing_cycle, started_at, created_at, updated_at)
                VALUES
                    (?, ?, 'active', 'free', ?, ?, ?)
            ")->execute([$userId, $planId, $now, $now, $now]);

            $pdo->commit();
        } catch (Throwable $e) {
            // Only roll back if the transaction is still open
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Race condition: another request inserted the same email between our
            // uniqueness check above and this insert. Return the same friendly message.
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                return ['ok' => false, 'errors' => ['This email address is already registered.']];
            }
            throw $e;
        }

        // Protect against session fixation before writing the authenticated user ID
        session_regenerate_id(true);
        self::setUserId($userId);
        $_SESSION['session_started_at'] = gmdate('Y-m-d H:i:s');

        $pdo->prepare(
            "UPDATE users SET last_login_at = ? WHERE id = ?"
        )->execute([gmdate('Y-m-d H:i:s'), $userId]);

        return ['ok' => true];
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    /**
     * Verify credentials, enforce status check, regenerate session.
     * Returns ['ok' => true] or ['ok' => false, 'error' => string].
     */
    public static function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        if ($email === '' || $password === '') {
            return ['ok' => false, 'error' => 'Please fill in all fields.'];
        }

        $stmt = Database::get()->prepare(
            "SELECT id, password_hash, status FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Same message for unknown email and wrong password (no user enumeration)
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        if ($user['status'] === 'suspended') {
            return ['ok' => false, 'error' => 'This account has been suspended. Please contact support.'];
        }

        // Protect against session fixation
        session_regenerate_id(true);
        self::setUserId((int) $user['id']);
        $_SESSION['session_started_at'] = gmdate('Y-m-d H:i:s');

        Database::get()->prepare(
            "UPDATE users SET last_login_at = ? WHERE id = ?"
        )->execute([gmdate('Y-m-d H:i:s'), $user['id']]);

        return ['ok' => true];
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public static function logout(): void
    {
        // Clear all session data (including CSRF token)
        $_SESSION = [];

        // Expire the session cookie, preserving all original security attributes
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
        self::$cachedUser = null;
    }

    /** Clear the per-request user cache so the next call re-fetches from the DB. */
    public static function clearCache(): void
    {
        self::$cachedUser = null;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function setUserId(int $id): void
    {
        $_SESSION['user_id'] = $id;
        self::$cachedUser    = null;
    }

    /** @return string[] */
    private static function validateRegistration(string $email, string $password, string $confirm): array
    {
        $errors = [];

        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if ($confirm === '') {
            $errors[] = 'Password confirmation is required.';
        } elseif ($password !== '' && $password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        return $errors;
    }
}
