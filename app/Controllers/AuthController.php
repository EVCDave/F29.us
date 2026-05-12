<?php
declare(strict_types=1);

class AuthController
{
    // ── Login ─────────────────────────────────────────────────────────────────

    public function loginPage(): void
    {
        // Already logged in — send to dashboard
        if (AuthService::isLoggedIn()) {
            redirect('/dashboard');
        }

        View::render('auth/login', [
            'pageTitle' => 'Login — f29.us Dynamic QR',
            'errors'    => [],
            'oldEmail'  => '',
        ]);
    }

    public function loginSubmit(): void
    {
        $email    = $_POST['email']    ?? '';
        $password = $_POST['password'] ?? '';

        $result = AuthService::login($email, $password);

        if (!$result['ok']) {
            View::render('auth/login', [
                'pageTitle' => 'Login — f29.us Dynamic QR',
                'errors'    => [$result['error']],
                'oldEmail'  => strtolower(trim($email)),
            ]);
            return;
        }

        redirect('/dashboard');
    }

    // ── Registration ──────────────────────────────────────────────────────────

    public function registerPage(): void
    {
        if (AuthService::isLoggedIn()) {
            redirect('/dashboard');
        }

        View::render('auth/register', [
            'pageTitle' => 'Create an Account — f29.us Dynamic QR',
            'errors'    => [],
            'oldEmail'  => '',
        ]);
    }

    public function registerSubmit(): void
    {
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

        redirect('/dashboard');
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function logout(): void
    {
        AuthService::logout();
        redirect('/login');
    }
}
