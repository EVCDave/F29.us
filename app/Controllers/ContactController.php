<?php
declare(strict_types=1);

/**
 * Public contact form + lightweight admin intake.
 *
 * Not a ticketing system — no threading, no public ticket numbers, no
 * customer-facing portal. Submissions land in `contact_messages` and admins
 * review them via `AdminContactController`.
 */
class ContactController
{
    public const CATEGORIES = [
        'general'   => 'General question',
        'billing'   => 'Billing / subscription',
        'technical' => 'Technical support',
        'account'   => 'Account access',
        'problem'   => 'Report a problem',
        'other'     => 'Other',
    ];

    private const MIN_FORM_SECONDS         = 3;
    private const RATE_LIMIT_WINDOW_SECS   = 3600;
    private const RATE_LIMIT_PER_IP_HOUR   = 5;
    private const RATE_LIMIT_PER_EMAIL_HR  = 3;

    public function show(array $params = []): void
    {
        $this->render([
            'input'      => $this->prefillFromUser(),
            'errors'     => [],
            'submitted'  => false,
        ]);
    }

    public function submit(array $params = []): void
    {
        CsrfService::requireValid();

        $user = AuthService::currentUser();
        $now  = time();

        $name     = trim((string) ($_POST['name']     ?? ''));
        $email    = strtolower(trim((string) ($_POST['email']    ?? '')));
        $category = trim((string) ($_POST['category'] ?? ''));
        $subject  = trim((string) ($_POST['subject']  ?? ''));
        $message  = (string) ($_POST['message']  ?? '');

        $honeypot       = (string) ($_POST['website']         ?? '');
        $formStartedAt  = (int)    ($_POST['form_started_at'] ?? 0);

        // ── Silent-drop paths (bot-shaped requests) ──────────────────────────
        // For obvious bots we show the same success page and skip persistence.
        // Returning a distinct error would help bots tune their submissions.
        if ($honeypot !== '') {
            $this->renderConfirmation();
            return;
        }
        if ($formStartedAt <= 0 || ($now - $formStartedAt) < self::MIN_FORM_SECONDS) {
            $this->renderConfirmation();
            return;
        }

        $input = [
            'name'     => $name,
            'email'    => $email,
            'category' => $category,
            'subject'  => $subject,
            'message'  => $message,
        ];

        $errors = $this->validate($input);
        if (!empty($errors)) {
            $this->render([
                'input'     => $input,
                'errors'    => $errors,
                'submitted' => false,
            ]);
            return;
        }

        $ipHash = LoginThrottleService::hashIp($_SERVER['REMOTE_ADDR'] ?? null);
        if ($this->isRateLimited($ipHash, $email)) {
            $this->render([
                'input'     => $input,
                'errors'    => ['Too many contact messages have been submitted recently. Please try again later.'],
                'submitted' => false,
            ]);
            return;
        }

        $userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000);

        try {
            $pdo = Database::get();
            $pdo->prepare("
                INSERT INTO contact_messages
                    (user_id, name, email, category, subject, message,
                     status, ip_hash, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'new', ?, ?, ?)
            ")->execute([
                $user['id'] ?? null,
                $name,
                $email,
                $category,
                $subject,
                $message,
                $ipHash,
                $userAgent !== '' ? $userAgent : null,
                gmdate('Y-m-d H:i:s'),
            ]);

            $messageId = (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('[ContactController] DB insert failed: ' . $e->getMessage());
            $this->render([
                'input'     => $input,
                'errors'    => ['We were unable to send your message. Please try again later or email us directly.'],
                'submitted' => false,
            ]);
            return;
        }

        // Best-effort notification — never block the user on mail failures.
        try {
            NotificationService::contactMessageSubmitted($messageId);
        } catch (Throwable $e) {
            error_log('[ContactController] Notification failed for message #'
                . $messageId . ': ' . $e->getMessage());
        }

        $this->renderConfirmation();
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /** @return array<int, string> */
    private function validate(array $input): array
    {
        $errors = [];

        $name    = (string) $input['name'];
        $email   = (string) $input['email'];
        $cat     = (string) $input['category'];
        $subject = (string) $input['subject'];
        $message = (string) $input['message'];

        if ($name === '') {
            $errors[] = 'Name is required.';
        } elseif (mb_strlen($name) > 200) {
            $errors[] = 'Name must be 200 characters or fewer.';
        }

        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (!isset(self::CATEGORIES[$cat])) {
            $errors[] = 'Please choose a category.';
        }

        if ($subject === '') {
            $errors[] = 'Subject is required.';
        } elseif (mb_strlen($subject) > 200) {
            $errors[] = 'Subject must be 200 characters or fewer.';
        }

        $messageTrimmed = trim($message);
        if ($messageTrimmed === '') {
            $errors[] = 'Message is required.';
        } elseif (mb_strlen($message) > 5000) {
            $errors[] = 'Message must be 5000 characters or fewer.';
        }

        // Reject null bytes / hard control chars (allow tab + CR + LF).
        foreach (['name', 'email', 'category', 'subject', 'message'] as $field) {
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', (string) $input[$field]) === 1) {
                $errors[] = 'One or more fields contain characters that are not allowed.';
                break;
            }
        }

        return $errors;
    }

    private function isRateLimited(?string $ipHash, string $email): bool
    {
        $since = gmdate('Y-m-d H:i:s', time() - self::RATE_LIMIT_WINDOW_SECS);
        $pdo   = Database::get();

        if ($ipHash !== null) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM contact_messages WHERE ip_hash = ? AND created_at >= ?"
            );
            $stmt->execute([$ipHash, $since]);
            if ((int) $stmt->fetchColumn() >= self::RATE_LIMIT_PER_IP_HOUR) {
                return true;
            }
        }

        if ($email !== '') {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM contact_messages WHERE email = ? AND created_at >= ?"
            );
            $stmt->execute([$email, $since]);
            if ((int) $stmt->fetchColumn() >= self::RATE_LIMIT_PER_EMAIL_HR) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prefill name + email for logged-in users; empty strings otherwise.
     */
    private function prefillFromUser(): array
    {
        $defaults = [
            'name' => '', 'email' => '', 'category' => 'general', 'subject' => '', 'message' => '',
        ];

        $user = AuthService::currentUser();
        if ($user === null) {
            return $defaults;
        }

        // Best display name: display_name > "first last" > email local-part.
        $name = trim((string) ($user['display_name'] ?? ''));
        if ($name === '') {
            $name = trim(trim((string) ($user['first_name'] ?? '')) . ' '
                       . trim((string) ($user['last_name']  ?? '')));
        }
        if ($name === '') {
            $name = explode('@', (string) ($user['email'] ?? ''))[0] ?? '';
        }

        $defaults['name']  = $name;
        $defaults['email'] = (string) ($user['email'] ?? '');
        return $defaults;
    }

    private function render(array $state): void
    {
        View::render('policy/contact', [
            'pageTitle'      => 'Contact — f29.us Dynamic QR',
            'categories'     => self::CATEGORIES,
            'input'          => $state['input']     ?? $this->prefillFromUser(),
            'errors'         => $state['errors']    ?? [],
            'submitted'      => (bool) ($state['submitted'] ?? false),
            'formStartedAt'  => time(),
            'supportEmail'   => $_ENV['SUPPORT_EMAIL'] ?? 'support@f29.us',
            'abuseEmail'     => $_ENV['ABUSE_EMAIL']   ?? 'abuse@f29.us',
            'privacyEmail'   => $_ENV['PRIVACY_EMAIL'] ?? 'privacy@f29.us',
        ]);
    }

    private function renderConfirmation(): void
    {
        $this->render([
            'input'     => ['name' => '', 'email' => '', 'category' => 'general', 'subject' => '', 'message' => ''],
            'errors'    => [],
            'submitted' => true,
        ]);
    }
}
