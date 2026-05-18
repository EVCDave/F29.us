<?php
declare(strict_types=1);

class PasswordResetController
{
    // ── Forgot password form ──────────────────────────────────────────────────

    public function forgotPage(array $params = []): void
    {
        View::render('auth/forgot_password', [
            'pageTitle' => 'Forgot Password — F29 QR Codes System',
            'submitted' => false,
            'error'     => '',
        ]);
    }

    public function forgotSubmit(array $params = []): void
    {
        CsrfService::requireValid();

        $email = trim($_POST['email'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            View::render('auth/forgot_password', [
                'pageTitle' => 'Forgot Password — F29 QR Codes System',
                'submitted' => false,
                'error'     => 'Please enter a valid email address.',
            ]);
            return;
        }

        PasswordResetService::requestReset($email);

        // Always show the same message regardless of whether the account exists
        View::render('auth/forgot_password', [
            'pageTitle' => 'Forgot Password — F29 QR Codes System',
            'submitted' => true,
            'error'     => '',
        ]);
    }

    // ── Reset password form ───────────────────────────────────────────────────

    public function resetPage(array $params = []): void
    {
        $rawToken = trim($_GET['token'] ?? '');
        $context  = PasswordResetService::getValidTokenContext($rawToken);

        View::render('auth/reset_password', [
            'pageTitle' => 'Reset Password — F29 QR Codes System',
            'valid'     => $context !== null,
            'token'     => $rawToken,
            'error'     => '',
            'success'   => false,
        ]);
    }

    public function resetSubmit(array $params = []): void
    {
        CsrfService::requireValid();

        $rawToken = trim($_POST['token']           ?? '');
        $newPass  = $_POST['new_password']         ?? '';
        $confirm  = $_POST['new_password_confirm'] ?? '';

        $result = PasswordResetService::resetPassword($rawToken, $newPass, $confirm);

        if (!$result['ok']) {
            // Re-check token validity so form shows or hides the password fields correctly
            View::render('auth/reset_password', [
                'pageTitle' => 'Reset Password — F29 QR Codes System',
                'valid'     => PasswordResetService::getValidTokenContext($rawToken) !== null,
                'token'     => $rawToken,
                'error'     => $result['message'],
                'success'   => false,
            ]);
            return;
        }

        View::render('auth/reset_password', [
            'pageTitle' => 'Reset Password — F29 QR Codes System',
            'valid'     => false,
            'token'     => '',
            'error'     => '',
            'success'   => true,
        ]);
    }
}
