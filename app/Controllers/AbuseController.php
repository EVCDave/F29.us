<?php
declare(strict_types=1);

/**
 * Public abuse-report intake.
 *
 * Structured form submissions land in the same `contact_messages` table that
 * Phase 40 introduced, with `category='abuse'`. The structured fields
 * (reported_url / destination_url / abuse_type) are formatted into the
 * message body — no new table or schema change.
 *
 * Admins review abuse reports in the same `/admin/contact-messages` workflow,
 * with extra highlighting for abuse rows.
 */
class AbuseController
{
    public const ABUSE_TYPES = [
        'phishing'      => 'Phishing or credential theft',
        'malware'       => 'Malware or harmful download',
        'spam'          => 'Spam',
        'impersonation' => 'Impersonation or deception',
        'illegal'       => 'Illegal content',
        'harassment'    => 'Harassment or threats',
        'other'         => 'Other',
    ];

    private const MIN_FORM_SECONDS         = 3;
    private const RATE_LIMIT_WINDOW_SECS   = 3600;
    private const RATE_LIMIT_PER_IP_HOUR   = 5;
    private const RATE_LIMIT_PER_EMAIL_HR  = 3;
    private const REPORTED_URL_MAX_LEN     = 2048;
    private const DESTINATION_URL_MAX_LEN  = 2048;
    private const MESSAGE_MAX_LEN          = 5000;

    public function show(array $params = []): void
    {
        $this->render([
            'input'     => $this->prefillFromUser(),
            'errors'    => [],
            'submitted' => false,
        ]);
    }

    public function submit(array $params = []): void
    {
        CsrfService::requireValid();

        $user = AuthService::currentUser();
        $now  = time();

        $name           = trim((string) ($_POST['name']            ?? ''));
        $email          = strtolower(trim((string) ($_POST['email']           ?? '')));
        $reportedUrl    = trim((string) ($_POST['reported_url']    ?? ''));
        $destinationUrl = trim((string) ($_POST['destination_url'] ?? ''));
        $abuseType      = trim((string) ($_POST['abuse_type']      ?? ''));
        $message        = (string)        ($_POST['message']        ?? '');

        $honeypot      = (string) ($_POST['website']         ?? '');
        $formStartedAt = (int)    ($_POST['form_started_at'] ?? 0);

        // ── Silent-drop paths (bot-shaped requests). Identical UX to /contact:
        // showing the same success page denies bots useful tuning signal.
        if ($honeypot !== '') {
            $this->renderConfirmation();
            return;
        }
        if ($formStartedAt <= 0 || ($now - $formStartedAt) < self::MIN_FORM_SECONDS) {
            $this->renderConfirmation();
            return;
        }

        $input = [
            'name'            => $name,
            'email'           => $email,
            'reported_url'    => $reportedUrl,
            'destination_url' => $destinationUrl,
            'abuse_type'      => $abuseType,
            'message'         => $message,
        ];

        $errors = $this->validate($input);
        if (!empty($errors)) {
            $this->render(['input' => $input, 'errors' => $errors, 'submitted' => false]);
            return;
        }

        $ipHash = LoginThrottleService::hashIp($_SERVER['REMOTE_ADDR'] ?? null);
        if ($this->isRateLimited($ipHash, $email)) {
            $this->render([
                'input'     => $input,
                'errors'    => ['Too many abuse reports have been submitted recently. Please try again later.'],
                'submitted' => false,
            ]);
            return;
        }

        $abuseTypeLabel = self::ABUSE_TYPES[$abuseType];
        $subject        = 'Abuse report: ' . $abuseTypeLabel;
        $body           = $this->formatBody($reportedUrl, $destinationUrl, $abuseTypeLabel, $message);
        $userAgent      = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000);

        try {
            $pdo = Database::get();
            $pdo->prepare("
                INSERT INTO contact_messages
                    (user_id, name, email, category, subject, message,
                     status, ip_hash, user_agent, created_at)
                VALUES (?, ?, ?, 'abuse', ?, ?, 'new', ?, ?, ?)
            ")->execute([
                $user['id'] ?? null,
                $name,
                $email,
                $subject,
                $body,
                $ipHash,
                $userAgent !== '' ? $userAgent : null,
                gmdate('Y-m-d H:i:s'),
            ]);

            $messageId = (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('[AbuseController] DB insert failed: ' . $e->getMessage());
            $this->render([
                'input'     => $input,
                'errors'    => ['We were unable to submit your abuse report. Please try again later or email abuse directly.'],
                'submitted' => false,
            ]);
            return;
        }

        try {
            NotificationService::abuseReportSubmitted($messageId);
        } catch (Throwable $e) {
            error_log('[AbuseController] Notification failed for abuse report #'
                . $messageId . ': ' . $e->getMessage());
        }

        $this->renderConfirmation();
    }

    // ── Validation ──────────────────────────────────────────────────────────

    /** @return array<int, string> */
    private function validate(array $input): array
    {
        $errors = [];

        $name           = (string) $input['name'];
        $email          = (string) $input['email'];
        $reportedUrl    = (string) $input['reported_url'];
        $destinationUrl = (string) $input['destination_url'];
        $abuseType      = (string) $input['abuse_type'];
        $message        = (string) $input['message'];

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

        if ($reportedUrl === '') {
            $errors[] = 'Reported URL is required.';
        } elseif (mb_strlen($reportedUrl) > self::REPORTED_URL_MAX_LEN) {
            $errors[] = 'Reported URL must be 2048 characters or fewer.';
        } elseif (!$this->isHttpUrl($reportedUrl)) {
            $errors[] = 'Reported URL must be a valid URL starting with http:// or https://.';
        }

        if ($destinationUrl !== '') {
            if (mb_strlen($destinationUrl) > self::DESTINATION_URL_MAX_LEN) {
                $errors[] = 'Destination URL must be 2048 characters or fewer.';
            } elseif (!$this->isHttpUrl($destinationUrl)) {
                $errors[] = 'Destination URL must be a valid URL starting with http:// or https://.';
            }
        }

        if (!isset(self::ABUSE_TYPES[$abuseType])) {
            $errors[] = 'Please choose an abuse type.';
        }

        $messageTrimmed = trim($message);
        if ($messageTrimmed === '') {
            $errors[] = 'Description / evidence is required.';
        } elseif (mb_strlen($message) > self::MESSAGE_MAX_LEN) {
            $errors[] = 'Description / evidence must be 5000 characters or fewer.';
        }

        // Reject null bytes / hard control chars across every text field.
        foreach (['name', 'email', 'reported_url', 'destination_url', 'abuse_type', 'message'] as $field) {
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', (string) $input[$field]) === 1) {
                $errors[] = 'One or more fields contain characters that are not allowed.';
                break;
            }
        }

        return $errors;
    }

    private function isHttpUrl(string $url): bool
    {
        if (preg_match('/[\r\n\t\0]/', $url) === 1) {
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        return in_array($scheme, ['http', 'https'], true);
    }

    // ── Rate limit ──────────────────────────────────────────────────────────

    /**
     * Scoped to `category='abuse'` so submissions on /contact and /abuse don't
     * block each other. Matches the per-IP / per-email policy used by /contact.
     */
    private function isRateLimited(?string $ipHash, string $email): bool
    {
        $since = gmdate('Y-m-d H:i:s', time() - self::RATE_LIMIT_WINDOW_SECS);
        $pdo   = Database::get();

        if ($ipHash !== null) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM contact_messages
                WHERE ip_hash = ? AND category = 'abuse' AND created_at >= ?
            ");
            $stmt->execute([$ipHash, $since]);
            if ((int) $stmt->fetchColumn() >= self::RATE_LIMIT_PER_IP_HOUR) {
                return true;
            }
        }

        if ($email !== '') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM contact_messages
                WHERE email = ? AND category = 'abuse' AND created_at >= ?
            ");
            $stmt->execute([$email, $since]);
            if ((int) $stmt->fetchColumn() >= self::RATE_LIMIT_PER_EMAIL_HR) {
                return true;
            }
        }

        return false;
    }

    // ── Persistence helpers ─────────────────────────────────────────────────

    /**
     * Format the abuse-specific fields into the shared `message` column so the
     * admin detail view, the abuse notification email, and audit replay all
     * see the same readable structure.
     */
    private function formatBody(
        string $reportedUrl,
        string $destinationUrl,
        string $abuseTypeLabel,
        string $description
    ): string {
        return
              "Reported URL:\n" . $reportedUrl . "\n\n"
            . "Destination URL, if known:\n"
            . ($destinationUrl !== '' ? $destinationUrl : '(not provided)') . "\n\n"
            . "Abuse type:\n" . $abuseTypeLabel . "\n\n"
            . "Description / evidence:\n" . $description;
    }

    /** Default form values, prefilled when the user is logged in. */
    private function prefillFromUser(): array
    {
        $defaults = [
            'name'            => '',
            'email'           => '',
            'reported_url'    => '',
            'destination_url' => '',
            'abuse_type'      => '',
            'message'         => '',
        ];

        $user = AuthService::currentUser();
        if ($user === null) {
            return $defaults;
        }

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

    // ── Rendering ───────────────────────────────────────────────────────────

    private function render(array $state): void
    {
        View::render('policy/abuse', [
            'pageTitle'     => 'Report Abuse — f29.us Dynamic QR',
            'abuseTypes'    => self::ABUSE_TYPES,
            'input'         => $state['input']     ?? $this->prefillFromUser(),
            'errors'        => $state['errors']    ?? [],
            'submitted'     => (bool) ($state['submitted'] ?? false),
            'formStartedAt' => time(),
            'abuseEmail'    => $_ENV['ABUSE_EMAIL'] ?? 'abuse@f29.us',
        ]);
    }

    private function renderConfirmation(): void
    {
        $this->render([
            'input' => [
                'name' => '', 'email' => '', 'reported_url' => '',
                'destination_url' => '', 'abuse_type' => '', 'message' => '',
            ],
            'errors'    => [],
            'submitted' => true,
        ]);
    }
}
