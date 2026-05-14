<?php
declare(strict_types=1);

class EmailVerificationController
{
    // ── Token verification (public GET) ───────────────────────────────────────

    public function verify(array $params = []): void
    {
        $rawToken = trim($_GET['token'] ?? '');

        $result = EmailVerificationService::verifyToken($rawToken);

        View::render('auth/verify_email', [
            'pageTitle' => 'Email Verification — f29.us Dynamic QR',
            'success'   => $result['success'],
            'message'   => $result['message'],
            'purpose'   => $result['purpose'],
        ], 'main');
    }

    // ── Verify page (authenticated GET) ──────────────────────────────────────

    public function verifyPage(array $params = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::userId();

        $stmt = Database::get()->prepare(
            "SELECT email, email_verified_at, email_verification_required FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        View::render('account/verify_email', [
            'pageTitle' => 'Verify Your Email — f29.us Dynamic QR',
            'user'      => $user,
            'flash'     => $flash,
        ], 'main');
    }

    // ── Resend (authenticated POST) ───────────────────────────────────────────

    public function resend(array $params = []): void
    {
        CsrfService::requireValid();
        AuthService::requireAuth();

        $userId = (int) AuthService::userId();
        $result = EmailVerificationService::resendRegistrationVerification($userId);

        if ($result['ok']) {
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Verification email sent. Please check your inbox.'];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'text' => $result['reason']];
        }

        redirect('/account/verify-email');
    }
}
