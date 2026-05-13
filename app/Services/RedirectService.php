<?php
declare(strict_types=1);

class RedirectService
{
    /**
     * Resolve a slug, log a scan event for active links, and either redirect
     * or render the appropriate error/unavailable page. Always exits.
     */
    public static function handleSlug(string $slug): never
    {
        $slug = strtolower(trim($slug));

        if ($slug === '') {
            self::renderNotFound();
        }

        $stmt = Database::get()->prepare(
            "SELECT id, current_target_url, status FROM short_links WHERE slug = ? LIMIT 1"
        );
        $stmt->execute([$slug]);
        $link = $stmt->fetch();

        if (!$link) {
            self::renderNotFound();
        }

        if ($link['status'] !== 'active') {
            http_response_code(200);
            View::render('redirect/unavailable', [
                'pageTitle' => 'Link Unavailable — f29.us',
                'status'    => $link['status'],
            ]);
            exit;
        }

        $dest   = $link['current_target_url'] ?? '';
        $scheme = parse_url($dest, PHP_URL_SCHEME);

        if (
            $dest === ''
            || !in_array($scheme, ['http', 'https'], true)
            || !filter_var($dest, FILTER_VALIDATE_URL)
        ) {
            http_response_code(502);
            View::render('errors/forbidden', [
                'pageTitle' => 'Link Error — f29.us',
                'message'   => 'This link\'s destination is not currently available.',
            ]);
            exit;
        }

        self::logScanEvent((int) $link['id']);

        // Strip control characters as a final defence before setting the header
        $dest = str_replace(["\r", "\n", "\t"], '', $dest);
        header('Location: ' . $dest, true, 302);
        exit;
    }

    private static function logScanEvent(int $shortLinkId): void
    {
        $ip      = $_SERVER['REMOTE_ADDR']     ?? null;
        $ua      = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $referer = $_SERVER['HTTP_REFERER']    ?? null;

        $ipHash  = is_string($ip)      ? hash('sha256', $ip) : null;
        $ua      = is_string($ua)      ? substr($ua, 0, 1000) : null;
        $referer = is_string($referer) ? substr($referer, 0, 2000) : null;

        $isBot  = self::detectBot($ua);
        $device = $isBot ? 'bot' : self::detectDevice($ua);

        try {
            Database::get()->prepare("
                INSERT INTO scan_events
                    (short_link_id, scanned_at, ip_hash, user_agent, referer, device_type, bot_flag)
                VALUES (?, NOW(), ?, ?, ?, ?, ?)
            ")->execute([$shortLinkId, $ipHash, $ua, $referer, $device, $isBot ? 1 : 0]);
        } catch (Throwable) {
            // Logging failure must not break the redirect
        }
    }

    private static function detectBot(?string $ua): bool
    {
        if ($ua === null) {
            return false;
        }
        $lower = strtolower($ua);
        $keywords = [
            'bot', 'crawl', 'spider', 'slurp', 'fetch', 'scan',
            'facebookexternalhit', 'twitterbot', 'linkedinbot',
            'applebot', 'baiduspider', 'semrushbot', 'ahrefsbot',
        ];
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }
        return false;
    }

    private static function detectDevice(?string $ua): string
    {
        if ($ua === null) {
            return 'unknown';
        }
        if (preg_match('/tablet|ipad|kindle|silk|playbook/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/mobile|android|iphone|ipod|blackberry|windows phone/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    private static function renderNotFound(): never
    {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => '404 — Not Found']);
        exit;
    }
}
