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
            'pageTitle' => 'Login — F29 QR Codes System',
            'errors'    => [],
            'oldEmail'  => '',
        ]);
    }

    public function loginSubmit(array $params = []): void
    {
        CsrfService::requireValid();

        $email    = strtolower(trim($_POST['email']    ?? ''));
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember_me']);
        $ipHash   = LoginThrottleService::hashIp($_SERVER['REMOTE_ADDR'] ?? null);

        // DB-backed throttle: checked before touching credentials
        if (LoginThrottleService::isLockedOut($email, $ipHash)) {
            $this->renderLogin(
                ['Too many failed login attempts. Please try again later.'],
                $email
            );
            return;
        }

        $result = AuthService::login($email, $password, $remember);

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
            'pageTitle' => 'Create an Account — F29 QR Codes System',
            'errors'    => [],
            'oldEmail'  => '',
            'oldProfile' => [],
        ]);
    }

    public function registerSubmit(array $params = []): void
    {
        CsrfService::requireValid();

        $email    = $_POST['email']    ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm']  ?? '';

        $raw = [
            'first_name'   => trim($_POST['first_name']   ?? ''),
            'last_name'    => trim($_POST['last_name']    ?? ''),
            'display_name' => trim($_POST['display_name'] ?? ''),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'phone'        => trim($_POST['phone']        ?? ''),
            'timezone'     => trim($_POST['timezone']     ?? ''),
        ];

        $profileErrors = [];
        if (mb_strlen($raw['first_name'])   > 100) $profileErrors[] = 'First name must be 100 characters or fewer.';
        if (mb_strlen($raw['last_name'])    > 100) $profileErrors[] = 'Last name must be 100 characters or fewer.';
        if (mb_strlen($raw['display_name']) > 150) $profileErrors[] = 'Display name must be 150 characters or fewer.';
        if (mb_strlen($raw['company_name']) > 150) $profileErrors[] = 'Company must be 150 characters or fewer.';
        if (mb_strlen($raw['phone'])        >  50) $profileErrors[] = 'Phone must be 50 characters or fewer.';
        if (mb_strlen($raw['timezone'])     > 100) $profileErrors[] = 'Timezone must be 100 characters or fewer.';

        // Normalize: empty string → null
        $profile = array_map(fn($v) => $v !== '' ? $v : null, $raw);

        if (!empty($profileErrors)) {
            View::render('auth/register', [
                'pageTitle'  => 'Create an Account — F29 QR Codes System',
                'errors'     => $profileErrors,
                'oldEmail'   => strtolower(trim($email)),
                'oldProfile' => $raw,
            ]);
            return;
        }

        $result = AuthService::register($email, $password, $confirm, $profile);

        if (!$result['ok']) {
            View::render('auth/register', [
                'pageTitle'  => 'Create an Account — F29 QR Codes System',
                'errors'     => $result['errors'],
                'oldEmail'   => strtolower(trim($email)),
                'oldProfile' => $raw,
            ]);
            return;
        }

        // Rotate CSRF token after the session state change
        CsrfService::refresh();

        $newUserId = AuthService::userId();
        if ($newUserId) {
            EmailVerificationService::createRegistrationToken($newUserId, strtolower(trim($email)));
        }

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
            'pageTitle' => 'Login — F29 QR Codes System',
            'errors'    => $errors,
            'oldEmail'  => $oldEmail,
        ]);
    }
}
