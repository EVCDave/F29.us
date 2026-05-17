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

        // Structured linkage — populated even when no f29 short-link match is
        // found, so admins can still filter by reported_domain.
        $reportedDomain      = DomainBlocklistService::extractHost($reportedUrl);
        $relatedShortLinkId  = null;
        $relatedQrCodeId     = null;

        $slug = $this->extractF29Slug($reportedUrl);
        if ($slug !== null) {
            $stmt = Database::get()->prepare("
                SELECT sl.id AS short_link_id, qr.id AS qr_id
                FROM short_links sl
                LEFT JOIN qr_codes qr ON qr.short_link_id = sl.id
                WHERE sl.slug = ?
                LIMIT 1
            ");
            $stmt->execute([$slug]);
            $linkRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($linkRow) {
                $relatedShortLinkId = (int) $linkRow['short_link_id'];
                $relatedQrCodeId    = $linkRow['qr_id'] !== null ? (int) $linkRow['qr_id'] : null;
            }
        }

        try {
            $pdo = Database::get();
            $pdo->prepare("
                INSERT INTO contact_messages
                    (user_id, name, email, category, subject, message,
                     reported_url, reported_domain,
                     related_qr_code_id, related_short_link_id,
                     status, ip_hash, user_agent, created_at)
                VALUES (?, ?, ?, 'abuse', ?, ?, ?, ?, ?, ?, 'new', ?, ?, ?)
            ")->execute([
                $user['id'] ?? null,
                $name,
                $email,
                $subject,
                $body,
                $reportedUrl,
                $reportedDomain,
                $relatedQrCodeId,
                $relatedShortLinkId,
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

    /**
     * If the reported URL is an f29 short link, return its slug. Otherwise null.
     *
     * Hosts accepted: the host parts of APP_URL and QR_BASE_URL, plus the
     * literal `f29.us` and `www.f29.us` so admin tooling works on deployments
     * where those env vars are not set yet. Reserved slugs (system routes such
     * as /admin, /qr, /contact, /abuse) are intentionally excluded so a paste
     * of e.g. `https://f29.us/contact` does not falsely match.
     */
    private function extractF29Slug(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($host) || !is_string($path)) {
            return null;
        }

        $normalize = static fn(string $h): string => strtolower(
            str_starts_with(strtolower($h), 'www.') ? substr($h, 4) : $h
        );

        $candidate = $normalize($host);

        $allowed = ['f29.us'];
        foreach (['APP_URL', 'QR_BASE_URL'] as $envKey) {
            $envValue = $_ENV[$envKey] ?? '';
            if (!is_string($envValue) || $envValue === '') {
                continue;
            }
            $envHost = parse_url($envValue, PHP_URL_HOST);
            if (is_string($envHost) && $envHost !== '') {
                $allowed[] = $normalize($envHost);
            }
        }
        $allowed = array_values(array_unique($allowed));

        if (!in_array($candidate, $allowed, true)) {
            return null;
        }

        // Path must be a single segment like /abc123 — strip leading slash,
        // drop anything after the next slash, then validate against the slug
        // format and the reserved-slug list.
        $trimmed = ltrim($path, '/');
        if ($trimmed === '') {
            return null;
        }
        $firstSegment = explode('/', $trimmed, 2)[0];
        $slug         = SlugService::normalize($firstSegment);

        if ($slug === '' || SlugService::isReserved($slug)) {
            return null;
        }
        // Defer the character-pattern check to SlugService (min length 1 so a
        // short legitimate slug isn't rejected before we look it up).
        $check = SlugService::validateFormat($slug, 1, 64);
        if (!$check['valid']) {
            return null;
        }
        return $slug;
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
