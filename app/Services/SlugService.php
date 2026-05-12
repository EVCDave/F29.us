<?php
declare(strict_types=1);

class SlugService
{
    /** Allowed slug pattern: lowercase letters, digits, interior hyphens only. */
    private const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Character set used by the auto-generator (no hyphens in generated slugs). */
    private const GEN_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789';

    /** Maximum retries before generateUniqueSlug() gives up. */
    private const GEN_MAX_TRIES = 10;

    /** Lazy-loaded reserved slug list from config. */
    private static ?array $reservedSlugs = null;

    // ── Normalization ─────────────────────────────────────────────────────────

    /**
     * Lowercase + trim. All other methods call this first, but callers may
     * also call it directly before storing a slug in the database.
     */
    public static function normalize(string $slug): string
    {
        return strtolower(trim($slug));
    }

    // ── Single-concern checks ─────────────────────────────────────────────────

    /**
     * Returns true if the slug appears in the reserved-slugs config.
     * Comparison is against the already-normalized (lowercase) slug.
     */
    public static function isReserved(string $slug): bool
    {
        return in_array($slug, self::loadReservedSlugs(), true);
    }

    /**
     * Returns true if the slug is already in use in short_links.
     * Normalizes internally so callers never get a misleading result
     * from mixed-case or whitespace-padded input.
     */
    public static function exists(string $slug): bool
    {
        $slug = self::normalize($slug);
        $stmt = Database::get()->prepare(
            "SELECT 1 FROM short_links WHERE slug = ? LIMIT 1"
        );
        $stmt->execute([$slug]);
        return $stmt->fetch() !== false;
    }

    // ── Format validation ─────────────────────────────────────────────────────

    /**
     * Validate slug format and length.
     *
     * Returns ['valid' => true,  'errors' => []]
     *      or ['valid' => false, 'errors' => string[]]
     *
     * Assumes the slug has already been normalized.
     */
    public static function validateFormat(
        string $slug,
        int $minLength = 4,
        int $maxLength = 64
    ): array {
        if ($slug === '') {
            return ['valid' => false, 'errors' => ['Slug cannot be empty.']];
        }

        $errors = [];
        $len    = strlen($slug);

        if ($len < $minLength) {
            $errors[] = "Slug must be at least {$minLength} characters.";
        }

        if ($len > $maxLength) {
            $errors[] = "Slug cannot be longer than {$maxLength} characters.";
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        if (!preg_match(self::PATTERN, $slug)) {
            return ['valid' => false, 'errors' => [
                'Slug may only contain lowercase letters, numbers, and hyphens, '
                . 'and cannot start or end with a hyphen.',
            ]];
        }

        return ['valid' => true, 'errors' => []];
    }

    // ── Custom-slug validation (user + entitlement aware) ─────────────────────

    /**
     * Full validation pipeline for a user-supplied custom slug.
     *
     * 1. Normalize
     * 2. Check can_use_custom_slug entitlement
     * 3. Resolve plan-specific min/max length
     * 4. Validate format + length
     * 5. Reject reserved slugs
     * 6. Reject slugs already in use
     *
     * Returns ['valid' => true,  'slug' => $normalizedSlug, 'errors' => []]
     *      or ['valid' => false, 'slug' => $normalizedSlug, 'errors' => string[]]
     */
    public static function validateCustomSlugForUser(int $userId, string $slug): array
    {
        $slug = self::normalize($slug);

        // Entitlement gate
        if (!EntitlementService::isEnabled($userId, 'can_use_custom_slug')) {
            return [
                'valid'  => false,
                'slug'   => $slug,
                'errors' => ['Custom slugs are not available on your current plan.'],
            ];
        }

        // Plan-specific length bounds (fall back to service defaults)
        $minLength = (int) EntitlementService::getValue($userId, 'custom_slug_min_length', 4);
        $maxLength = (int) EntitlementService::getValue($userId, 'custom_slug_max_length', 64);

        // Format + length
        $formatResult = self::validateFormat($slug, $minLength, $maxLength);
        if (!$formatResult['valid']) {
            return array_merge($formatResult, ['slug' => $slug]);
        }

        // Reserved word
        if (self::isReserved($slug)) {
            return [
                'valid'  => false,
                'slug'   => $slug,
                'errors' => ['This slug is reserved and cannot be used.'],
            ];
        }

        // Uniqueness
        if (self::exists($slug)) {
            return [
                'valid'  => false,
                'slug'   => $slug,
                'errors' => ['This slug is already taken.'],
            ];
        }

        return ['valid' => true, 'slug' => $slug, 'errors' => []];
    }

    // ── Auto-generation ───────────────────────────────────────────────────────

    /**
     * Generate a random, unique, URL-safe lowercase alphanumeric slug.
     *
     * Generated slugs intentionally contain no hyphens to distinguish them
     * visually from human-chosen slugs.
     *
     * @throws RuntimeException after GEN_MAX_TRIES collisions (extremely rare).
     */
    public static function generateUniqueSlug(int $length = 6): string
    {
        $chars   = self::GEN_CHARS;
        $charLen = strlen($chars);

        for ($attempt = 0; $attempt < self::GEN_MAX_TRIES; $attempt++) {
            $slug = '';
            for ($i = 0; $i < $length; $i++) {
                $slug .= $chars[random_int(0, $charLen - 1)];
            }

            if (!self::exists($slug) && !self::isReserved($slug)) {
                return $slug;
            }
        }

        throw new RuntimeException(
            "Could not generate a unique slug of length {$length} after "
            . self::GEN_MAX_TRIES . ' attempts. Consider increasing length.'
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function loadReservedSlugs(): array
    {
        if (self::$reservedSlugs === null) {
            self::$reservedSlugs = require CONFIG_PATH . '/reserved_slugs.php';
        }
        return self::$reservedSlugs;
    }
}
