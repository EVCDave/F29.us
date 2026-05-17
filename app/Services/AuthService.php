<?php
declare(strict_types=1);

class AuthService
{
    private static ?array $cachedUser = null;

    // ── Remember-me constants ────────────────────────────────────────────────
    public const REMEMBER_COOKIE              = 'f29_remember';
    public const REMEMBER_TTL_DAYS            = 30;
    private const SELECTOR_BYTES              = 16;   // 32 hex chars
    private const TOKEN_BYTES                 = 32;   // 64 hex chars
    // Tolerate the briefly-stale previous selector/token across concurrent
    // browser requests during automatic session restoration. Just long enough
    // to cover a typical batch of in-flight tabs.
    private const REMEMBER_ROTATION_GRACE_SECONDS = 60;

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

        // If no session user but a remember-me cookie is present, try to
        // restore the authenticated session from the persistent token.
        // Runs at most once per request and is safe to call on every page —
        // it returns immediately when a session user already exists.
        self::tryRestoreFromRememberCookie();
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
     *
     * $profile keys: first_name, last_name, display_name, company_name, phone, timezone.
     * All values must already be trimmed and normalized (empty string → null).
     */
    public static function register(string $email, string $password, string $confirm, array $profile = []): array
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
                INSERT INTO users
                    (email, password_hash, status, email_verification_required,
                     first_name, last_name, display_name, company_name, phone, timezone,
                     created_at, updated_at)
                VALUES (?, ?, 'active', 1, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $email, $hash,
                $profile['first_name']   ?? null,
                $profile['last_name']    ?? null,
                $profile['display_name'] ?? null,
                $profile['company_name'] ?? null,
                $profile['phone']        ?? null,
                $profile['timezone']     ?? null,
                $now, $now,
            ]);

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
     *
     * If $remember is true, a 30-day persistent-login token is also issued.
     */
    public static function login(string $email, string $password, bool $remember = false): array
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

        if ($remember) {
            self::issueRememberToken((int) $user['id']);
        }

        return ['ok' => true];
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public static function logout(): void
    {
        // Revoke the persistent-login token tied to this browser, if any.
        self::clearCurrentRememberToken();

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

    // ── Remember-me (persistent-login token) ─────────────────────────────────

    /**
     * Issue a fresh remember-me token for the given user and set the cookie.
     *
     * Only the SHA-256 of the secret half is stored; the raw secret is only
     * ever in the cookie. The cookie value is `selector:token` so the row can
     * be located in O(1) without exposing the secret in queries.
     */
    private static function issueRememberToken(int $userId): void
    {
        $selector  = bin2hex(random_bytes(self::SELECTOR_BYTES));
        $token     = bin2hex(random_bytes(self::TOKEN_BYTES));
        $tokenHash = hash('sha256', $token);

        $expiresAt = gmdate('Y-m-d H:i:s', time() + 86400 * self::REMEMBER_TTL_DAYS);
        $now       = gmdate('Y-m-d H:i:s');

        $userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $ipHash    = self::hashIpForRemember($_SERVER['REMOTE_ADDR'] ?? null);

        Database::get()->prepare("
            INSERT INTO remember_tokens
                (user_id, selector, token_hash, expires_at, last_used_at,
                 created_at, user_agent, ip_hash)
            VALUES (?, ?, ?, ?, NULL, ?, ?, ?)
        ")->execute([$userId, $selector, $tokenHash, $expiresAt, $now, $userAgent ?: null, $ipHash]);

        self::setRememberCookie($selector . ':' . $token, time() + 86400 * self::REMEMBER_TTL_DAYS);
    }

    /**
     * Look at $_COOKIE for a valid remember-me token. If one is found and the
     * referenced user is still active, populate the session and rotate the
     * token. Silently expires any invalid/expired cookie.
     *
     * Concurrency: after a successful restore we rotate the row to new
     * selector/token AND keep the previous selector+hash for a short grace
     * window (REMEMBER_ROTATION_GRACE_SECONDS). A second request that arrives
     * with the old cookie during that window matches via `previous_selector`
     * and is restored without rotating again or expiring the cookie.
     */
    private static function tryRestoreFromRememberCookie(): void
    {
        if (self::isLoggedIn()) {
            return;
        }

        $raw = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        if ($raw === '') {
            return;
        }

        // Cookie format: 32-hex selector : 64-hex token. Anything else is junk.
        if (!preg_match('/^([a-f0-9]{32}):([a-f0-9]{64})$/', $raw, $m)) {
            self::expireRememberCookie();
            return;
        }
        $selector = $m[1];
        $token    = $m[2];

        // Match either the active selector OR the just-rotated previous selector.
        $stmt = Database::get()->prepare("
            SELECT rt.id, rt.user_id,
                   rt.selector, rt.token_hash, rt.expires_at,
                   rt.previous_selector, rt.previous_token_hash, rt.previous_valid_until,
                   u.status
            FROM remember_tokens rt
            JOIN users u ON u.id = rt.user_id
            WHERE rt.selector = ? OR rt.previous_selector = ?
            LIMIT 1
        ");
        $stmt->execute([$selector, $selector]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            self::expireRememberCookie();
            return;
        }

        $matchedCurrent  = hash_equals((string) $row['selector'], $selector);
        $matchedPrevious = !$matchedCurrent
                        && $row['previous_selector'] !== null
                        && hash_equals((string) $row['previous_selector'], $selector);

        // Row's overall expires_at applies to either match — drop the whole
        // row if the token is fully past its 30-day TTL.
        if (strtotime((string) $row['expires_at']) <= time()) {
            self::deleteRememberTokenById((int) $row['id']);
            self::expireRememberCookie();
            return;
        }

        if (($row['status'] ?? '') === 'suspended') {
            // Suspended users cannot be restored from either selector position.
            self::deleteRememberTokenById((int) $row['id']);
            self::expireRememberCookie();
            return;
        }

        if ($matchedCurrent) {
            // Constant-time compare. A mismatch with a valid selector likely
            // means the cookie was tampered with — delete the token so an
            // attacker who has the selector but not the secret can't keep
            // probing.
            if (!hash_equals((string) $row['token_hash'], hash('sha256', $token))) {
                self::deleteRememberTokenById((int) $row['id']);
                self::expireRememberCookie();
                return;
            }

            // Restore + rotate.
            session_regenerate_id(true);
            self::setUserId((int) $row['user_id']);
            $_SESSION['session_started_at'] = gmdate('Y-m-d H:i:s');
            self::rotateRememberToken((int) $row['id'], (int) $row['user_id']);
            return;
        }

        if ($matchedPrevious) {
            // Previous-selector path: tolerate concurrent requests for a short
            // grace window. A stale-or-bad previous cookie must NEVER delete
            // the valid current row — only the cookie is expired in that case.
            $validUntil = $row['previous_valid_until'] !== null
                ? strtotime((string) $row['previous_valid_until'])
                : 0;

            if ($validUntil < time()) {
                // Grace window has closed.
                self::expireRememberCookie();
                return;
            }

            if (!hash_equals((string) $row['previous_token_hash'], hash('sha256', $token))) {
                // Bad previous-token: do not delete the row, just expire the cookie.
                self::expireRememberCookie();
                return;
            }

            // Restore session WITHOUT rotating again — the current selector/
            // token are already in flight from the first concurrent request.
            // We deliberately leave the browser's stale remember cookie alone:
            // the established PHP session will carry subsequent requests, and
            // the stale cookie expires naturally when the grace window ends.
            session_regenerate_id(true);
            self::setUserId((int) $row['user_id']);
            $_SESSION['session_started_at'] = gmdate('Y-m-d H:i:s');
            return;
        }

        // Should not be reachable: the SQL `OR` guarantees one of the matches.
        // Belt-and-braces: treat as junk.
        self::expireRememberCookie();
    }

    /**
     * Replace the existing row's selector/token_hash with fresh values, stash
     * the old selector/token_hash in the previous_* fields for a short grace
     * window so concurrent requests with the old cookie can still resolve,
     * and issue a new cookie. Bumps expires_at to now + TTL and stamps
     * last_used_at.
     */
    private static function rotateRememberToken(int $tokenId, int $userId): void
    {
        $pdo = Database::get();

        // Need the row's current selector/token_hash so we can move them into
        // the previous_* fields atomically with the new values.
        $stmt = $pdo->prepare(
            "SELECT selector, token_hash FROM remember_tokens WHERE id = ? AND user_id = ? LIMIT 1"
        );
        $stmt->execute([$tokenId, $userId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            // Row vanished between the lookup and rotation — nothing to do.
            return;
        }

        $newSelector  = bin2hex(random_bytes(self::SELECTOR_BYTES));
        $newToken     = bin2hex(random_bytes(self::TOKEN_BYTES));
        $newTokenHash = hash('sha256', $newToken);
        $newExpiresAt = gmdate('Y-m-d H:i:s', time() + 86400 * self::REMEMBER_TTL_DAYS);
        $now          = gmdate('Y-m-d H:i:s');
        $graceUntil   = gmdate('Y-m-d H:i:s', time() + self::REMEMBER_ROTATION_GRACE_SECONDS);

        $pdo->prepare("
            UPDATE remember_tokens
            SET previous_selector     = ?,
                previous_token_hash   = ?,
                previous_valid_until  = ?,
                selector              = ?,
                token_hash            = ?,
                expires_at            = ?,
                last_used_at          = ?
            WHERE id = ? AND user_id = ?
        ")->execute([
            $current['selector'],
            $current['token_hash'],
            $graceUntil,
            $newSelector,
            $newTokenHash,
            $newExpiresAt,
            $now,
            $tokenId,
            $userId,
        ]);

        self::setRememberCookie(
            $newSelector . ':' . $newToken,
            time() + 86400 * self::REMEMBER_TTL_DAYS
        );
    }

    /**
     * Logout helper: delete the row referenced by the current remember cookie
     * (if any) and expire the cookie itself. Matches both the current selector
     * and the previous (grace-window) selector so logging out from a stale tab
     * still revokes the row.
     */
    private static function clearCurrentRememberToken(): void
    {
        $raw = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        if ($raw !== '' && preg_match('/^([a-f0-9]{32}):/', $raw, $m)) {
            try {
                Database::get()->prepare(
                    "DELETE FROM remember_tokens WHERE selector = ? OR previous_selector = ?"
                )->execute([$m[1], $m[1]]);
            } catch (Throwable $e) {
                error_log('[RememberMe] Failed to delete token on logout: ' . $e->getMessage());
            }
        }
        self::expireRememberCookie();
    }

    private static function deleteRememberTokenById(int $tokenId): void
    {
        try {
            Database::get()->prepare(
                "DELETE FROM remember_tokens WHERE id = ?"
            )->execute([$tokenId]);
        } catch (Throwable $e) {
            error_log('[RememberMe] Failed to delete token by id: ' . $e->getMessage());
        }
    }

    private static function setRememberCookie(string $value, int $expires): void
    {
        $secure = str_starts_with($_ENV['APP_URL'] ?? '', 'https://');
        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        // Make the new cookie visible to any code in THIS request that re-reads
        // $_COOKIE (e.g. rotation immediately after restoration).
        $_COOKIE[self::REMEMBER_COOKIE] = $value;
    }

    private static function expireRememberCookie(): void
    {
        $secure = str_starts_with($_ENV['APP_URL'] ?? '', 'https://');
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires'  => time() - 42000,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[self::REMEMBER_COOKIE]);
    }

    /**
     * Hash an IP address for remember-token bookkeeping. Reuses the existing
     * login-throttle hash when available so we don't add a second secret.
     */
    private static function hashIpForRemember(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }
        if (method_exists('LoginThrottleService', 'hashIp')) {
            return LoginThrottleService::hashIp($ip);
        }
        return hash('sha256', $ip);
    }

    /**
     * Delete every fully-expired remember token AND clear stale previous_*
     * fields on rows whose grace window has closed. Called from cleanup.php.
     *
     * Returns the number of rows fully deleted. The previous-field clear is
     * a routine maintenance step and its row count is not surfaced — the
     * existing cleanup script only logs the delete count.
     */
    public static function deleteExpiredRememberTokens(): int
    {
        $pdo = Database::get();
        $now = gmdate('Y-m-d H:i:s');

        // Clear stale previous selector/hash on still-active rows.
        $pdo->prepare("
            UPDATE remember_tokens
            SET previous_selector    = NULL,
                previous_token_hash  = NULL,
                previous_valid_until = NULL
            WHERE previous_valid_until IS NOT NULL
              AND previous_valid_until < ?
        ")->execute([$now]);

        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE expires_at < ?");
        $stmt->execute([$now]);
        return $stmt->rowCount();
    }
}
