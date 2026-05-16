<?php
declare(strict_types=1);

/**
 * Builds and validates payload strings for static QR codes.
 *
 * Static QR codes are stateless: nothing is stored, nothing is tracked. This
 * service only turns submitted form fields into the raw text that gets encoded
 * into the QR image, plus a short human label for display.
 */
class StaticQrPayloadService
{
    public const TYPES = ['text', 'wifi', 'email', 'vcard'];
    public const MAX_PAYLOAD_LENGTH = 1200;
    public const VCARD_MAX = 1200;

    /**
     * @param array<string, mixed> $input Sanitized request fields (already trimmed by caller is fine,
     *                                    but we re-trim defensively here).
     * @return array{ok: bool, type: string, payload: string, label: string, errors: array<int, string>}
     */
    public static function build(array $input): array
    {
        $type = (string) ($input['type'] ?? '');
        if (!in_array($type, self::TYPES, true)) {
            return self::fail('text', ['Please choose a static QR type.']);
        }

        $result = match ($type) {
            'text'  => self::buildText($input),
            'wifi'  => self::buildWifi($input),
            'email' => self::buildEmail($input),
            'vcard' => self::buildVcard($input),
        };

        if (!$result['ok']) {
            return $result;
        }

        if (strlen($result['payload']) > self::MAX_PAYLOAD_LENGTH) {
            return self::fail($type, ['This QR payload is too large. Please shorten the content.']);
        }

        if (!self::isSafeContent($result['payload'])) {
            return self::fail($type, ['The QR payload contains characters that are not allowed.']);
        }

        return $result;
    }

    // ── Text / URL ───────────────────────────────────────────────────────────

    private static function buildText(array $input): array
    {
        $content = trim((string) ($input['content'] ?? ''));

        if ($content === '') {
            return self::fail('text', ['Content is required.']);
        }
        if (mb_strlen($content) > 1200) {
            return self::fail('text', ['Content must be 1200 characters or fewer.']);
        }
        if (!self::isSafeContent($content)) {
            return self::fail('text', ['Content contains characters that are not allowed.']);
        }

        $label = mb_strlen($content) > 60
            ? 'Text / URL: ' . mb_substr($content, 0, 60) . '…'
            : 'Text / URL: ' . $content;

        return [
            'ok'      => true,
            'type'    => 'text',
            'payload' => $content,
            'label'   => $label,
            'errors'  => [],
        ];
    }

    // ── Wi-Fi ────────────────────────────────────────────────────────────────

    private static function buildWifi(array $input): array
    {
        $ssid     = trim((string) ($input['ssid']     ?? ''));
        $password = (string) ($input['password'] ?? '');
        $security = (string) ($input['security'] ?? 'WPA');
        $hidden   = !empty($input['hidden']);

        $errors = [];

        if ($ssid === '') {
            $errors[] = 'SSID is required.';
        } elseif (mb_strlen($ssid) > 128) {
            $errors[] = 'SSID must be 128 characters or fewer.';
        }

        $allowedSecurity = ['WPA', 'WEP', 'nopass'];
        if (!in_array($security, $allowedSecurity, true)) {
            $errors[] = 'Security must be WPA, WEP, or nopass.';
        }

        if (($security === 'WPA' || $security === 'WEP') && $password === '') {
            $errors[] = 'Password is required for WPA/WEP networks.';
        }
        if (mb_strlen($password) > 256) {
            $errors[] = 'Password must be 256 characters or fewer.';
        }

        if (!self::isSafeContent($ssid) || !self::isSafeContent($password)) {
            $errors[] = 'Wi-Fi fields contain characters that are not allowed.';
        }

        if (!empty($errors)) {
            return self::fail('wifi', $errors);
        }

        $payload = 'WIFI:T:' . $security
                 . ';S:' . self::escapeWifi($ssid);

        if ($security !== 'nopass') {
            $payload .= ';P:' . self::escapeWifi($password);
        }

        $payload .= ';H:' . ($hidden ? 'true' : 'false') . ';;';

        return [
            'ok'      => true,
            'type'    => 'wifi',
            'payload' => $payload,
            'label'   => 'Wi-Fi: ' . $ssid,
            'errors'  => [],
        ];
    }

    private static function escapeWifi(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', ':'],
            ['\\\\', '\\;', '\\,', '\\:'],
            $value
        );
    }

    // ── Email ────────────────────────────────────────────────────────────────

    private static function buildEmail(array $input): array
    {
        $to      = trim((string) ($input['email_to']      ?? ''));
        $subject = trim((string) ($input['email_subject'] ?? ''));
        $body    = (string) ($input['email_body']         ?? '');

        $errors = [];

        if ($to === '') {
            $errors[] = 'Recipient email is required.';
        } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Recipient must be a valid email address.';
        }

        if (mb_strlen($subject) > 200) {
            $errors[] = 'Subject must be 200 characters or fewer.';
        }
        if (mb_strlen($body) > 1000) {
            $errors[] = 'Body must be 1000 characters or fewer.';
        }

        if (!self::isSafeContent($to) || !self::isSafeContent($subject) || !self::isSafeContent($body)) {
            $errors[] = 'Email fields contain characters that are not allowed.';
        }

        if (!empty($errors)) {
            return self::fail('email', $errors);
        }

        $payload = 'mailto:' . $to;
        $query = [];
        if ($subject !== '') {
            $query[] = 'subject=' . rawurlencode($subject);
        }
        if ($body !== '') {
            $query[] = 'body=' . rawurlencode($body);
        }
        if (!empty($query)) {
            $payload .= '?' . implode('&', $query);
        }

        return [
            'ok'      => true,
            'type'    => 'email',
            'payload' => $payload,
            'label'   => 'Email: ' . $to,
            'errors'  => [],
        ];
    }

    // ── vCard ────────────────────────────────────────────────────────────────

    private static function buildVcard(array $input): array
    {
        $firstName    = trim((string) ($input['first_name']   ?? ''));
        $lastName     = trim((string) ($input['last_name']    ?? ''));
        $displayName  = trim((string) ($input['display_name'] ?? ''));
        $company      = trim((string) ($input['company']      ?? ''));
        $title        = trim((string) ($input['title']        ?? ''));
        $phone        = trim((string) ($input['phone']        ?? ''));
        $email        = trim((string) ($input['email']        ?? ''));
        $website      = trim((string) ($input['website']      ?? ''));

        $errors = [];

        $hasAny = $firstName !== '' || $lastName !== '' || $displayName !== ''
               || $company   !== '' || $phone   !== '' || $email !== '' || $website !== '';
        if (!$hasAny) {
            $errors[] = 'At least one vCard field is required.';
        }

        $limits = [
            'first_name'   => [$firstName,   100],
            'last_name'    => [$lastName,    100],
            'display_name' => [$displayName, 200],
            'company'      => [$company,     200],
            'title'        => [$title,       200],
            'phone'        => [$phone,        50],
            'email'        => [$email,       255],
            'website'      => [$website,    2048],
        ];
        $fieldLabels = [
            'first_name' => 'First name', 'last_name' => 'Last name',
            'display_name' => 'Display name', 'company' => 'Company',
            'title' => 'Title', 'phone' => 'Phone', 'email' => 'Email', 'website' => 'Website',
        ];
        foreach ($limits as $key => [$value, $max]) {
            if (mb_strlen($value) > $max) {
                $errors[] = $fieldLabels[$key] . ' must be ' . $max . ' characters or fewer.';
            }
            if (!self::isSafeContent($value)) {
                $errors[] = $fieldLabels[$key] . ' contains characters that are not allowed.';
            }
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'vCard email must be a valid email address.';
        }
        if ($website !== '') {
            if (!filter_var($website, FILTER_VALIDATE_URL)) {
                $errors[] = 'Website must be a valid URL starting with http:// or https://.';
            } else {
                $scheme = parse_url($website, PHP_URL_SCHEME);
                if (!in_array($scheme, ['http', 'https'], true)) {
                    $errors[] = 'Website must start with http:// or https://.';
                }
            }
        }

        if (!empty($errors)) {
            return self::fail('vcard', $errors);
        }

        if ($displayName === '') {
            $displayName = trim($firstName . ' ' . $lastName);
        }
        if ($displayName === '') {
            $displayName = $company !== '' ? $company : $email;
        }
        if ($displayName === '') {
            $displayName = 'Contact';
        }

        $esc = static fn(string $v): string => self::escapeVcard($v);

        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:' . $esc($lastName) . ';' . $esc($firstName) . ';;;',
            'FN:' . $esc($displayName),
        ];
        if ($company !== '') $lines[] = 'ORG:' . $esc($company);
        if ($title   !== '') $lines[] = 'TITLE:' . $esc($title);
        if ($phone   !== '') $lines[] = 'TEL;TYPE=CELL:' . $esc($phone);
        if ($email   !== '') $lines[] = 'EMAIL:' . $esc($email);
        if ($website !== '') $lines[] = 'URL:' . $esc($website);
        $lines[] = 'END:VCARD';

        $payload = implode("\r\n", $lines);

        if (strlen($payload) > self::VCARD_MAX) {
            return self::fail(
                'vcard',
                ['The vCard is too large for this version of the static QR generator. Please shorten the fields.']
            );
        }

        return [
            'ok'      => true,
            'type'    => 'vcard',
            'payload' => $payload,
            'label'   => 'vCard: ' . $displayName,
            'errors'  => [],
        ];
    }

    private static function escapeVcard(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\r", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $value
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Reject null bytes and ASCII control characters except common whitespace
     * (tab, CR, LF). Applied to every user-submitted free-text field before
     * it reaches a payload string.
     */
    private static function isSafeContent(string $value): bool
    {
        if ($value === '') {
            return true;
        }
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 0;
    }

    private static function fail(string $type, array $errors): array
    {
        return [
            'ok'      => false,
            'type'    => $type,
            'payload' => '',
            'label'   => '',
            'errors'  => $errors,
        ];
    }
}
