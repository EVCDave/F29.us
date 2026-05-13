<?php
declare(strict_types=1);

class AuthController
{
    // ── Login ─────────────────────────────────────────────────────────────────

    public function loginPage(array $params = []): void
    {
        // Use currentUser() so a stale/suspended session is not treated as
        // authenticated and does not silently redirect the user away.
        if (AuthService::currentUser() !== null) {
            redirect('/dashboard');
        }

        View::render('auth/login', [
            'pageTitle' => 'Login — f29.us Dynamic QR',
            'errors'    => [],
            'oldEmail'  => '',
        ]);
    }

    public function loginSubmit(array $params = []): void
    {
        CsrfService::requireValid();

        $email    = strtolower(trim($_POST['email']    ?? ''));
        $password = $_POST['password'] ?? '';
        $ipHash   = LoginThrottleService::hashIp($_SERVER['REMOTE_ADDR'] ?? null);

        // DB-backed throttle: checked before touching credentials
        if (LoginThrottleService::isLockedOut($email, $ipHash)) {
            $this->renderLogin(
                ['Too many failed login attempts. Please try again later.'],
                $email
            );
            return;
        }

        $result = AuthService::login($email, $password);

        // Record attempt regardless of outcome
        LoginThrottleService::record($email, $ipHash, $result['ok']);

        if (!$result['ok']) {
            $this->renderLogin([$result['error']], $email);
            return;
        }

        // Rotate CSRF token after the session state change
        CsrfService::refresh();
        redirect('/dashboard');
    }

    // ── Registration ──────────────────────────────────────────────────────────

    public function registerPage(array $params = []): void
    {
        if (AuthService::currentUser() !== null) {
            redirect('/dashboard');
        }

        View::render('auth/register', [
            'pageTitle' => 'Create an Account — f29.us Dynamic QR',
            'errors'    => [],
            'oldEmail'  => '',
        ]);
    }

    public function registerSubmit(array $params = []): void
    {
        CsrfService::requireValid();

        $email    = $_POST['email']    ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm']  ?? '';

        $result = AuthService::register($email, $password, $confirm);

        if (!$result['ok']) {
            View::render('auth/register', [
                'pageTitle' => 'Create an Account — f29.us Dynamic QR',
                'errors'    => $result['errors'],
                'oldEmail'  => strtolower(trim($email)),
            ]);
            return;
        }

        // Rotate CSRF token after the session state change
        CsrfService::refresh();
        redirect('/dashboard');
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function logout(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::logout(); // clears $_SESSION (including CSRF token) and destroys session
        redirect('/login');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function renderLogin(array $errors, string $oldEmail): void
    {
        View::render('auth/login', [
            'pageTitle' => 'Login — f29.us Dynamic QR',
            'errors'    => $errors,
            'oldEmail'  => $oldEmail,
        ]);
    }
}
