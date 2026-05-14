<?php
declare(strict_types=1);

class DomainBlocklistService
{
    /**
     * Normalize a hostname: lowercase and strip leading www.
     * Call this on a bare hostname (no scheme or path).
     */
    public static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }
        return $domain;
    }

    /**
     * Returns true if $domain is a well-formed hostname suitable for blocklist storage.
     * Requires at least one dot, no spaces/underscores, no leading or trailing hyphens
     * per label, no empty labels, and a TLD of 2–63 alpha characters.
     */
    public static function isValidDomain(string $domain): bool
    {
        return (bool) preg_match(
            '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',
            $domain
        );
    }

    /** Extract the hostname from a full URL, lowercased. */
    public static function extractHost(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : null;
    }

    /** Check a full URL against the active blocklist. */
    public static function isBlockedUrl(string $url): array
    {
        $host = self::extractHost($url);
        if ($host === null) {
            return ['blocked' => false, 'domain' => null, 'reason' => null];
        }
        return self::isBlockedDomain($host);
    }

    /**
     * Check a hostname against the active blocklist.
     * If example.com is blocked, sub.example.com is also blocked.
     */
    public static function isBlockedDomain(string $host): array
    {
        $host = strtolower($host);
        $bare = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        // Build candidate list: exact host, without www., and each parent domain
        $candidates = array_values(array_unique(array_filter([$host, $bare])));

        $parts = explode('.', $bare);
        for ($i = 1; $i < count($parts) - 1; $i++) {
            $candidates[] = implode('.', array_slice($parts, $i));
        }

        $candidates = array_values(array_unique($candidates));

        if (empty($candidates)) {
            return ['blocked' => false, 'domain' => null, 'reason' => null];
        }

        $placeholders = implode(',', array_fill(0, count($candidates), '?'));
        $stmt = Database::get()->prepare("
            SELECT domain, reason
            FROM   blocked_domains
            WHERE  domain IN ($placeholders) AND is_active = 1
            LIMIT  1
        ");
        $stmt->execute($candidates);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return ['blocked' => true, 'domain' => $row['domain'], 'reason' => $row['reason']];
        }

        return ['blocked' => false, 'domain' => null, 'reason' => null];
    }
}
