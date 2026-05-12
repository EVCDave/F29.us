<?php
declare(strict_types=1);

class EntitlementService
{
    /**
     * Per-request cache keyed by user_id.
     * Holds the fully-merged feature map: ['feature_key' => typed_value, ...]
     */
    private static array $cache = [];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Resolve a single feature value for a user.
     *
     * Resolution order:
     *   1. Active, non-expired user_feature_overrides
     *   2. Active user_subscriptions → plan_features
     *   3. $fallback (caller-supplied)
     *   4. null
     */
    public static function getValue(int $userId, string $featureKey, mixed $fallback = null): mixed
    {
        $features = self::getAllForUser($userId);

        return array_key_exists($featureKey, $features)
            ? $features[$featureKey]
            : $fallback;
    }

    /**
     * Returns true if the feature key exists in the user's effective entitlement map.
     */
    public static function has(int $userId, string $featureKey): bool
    {
        return array_key_exists($featureKey, self::getAllForUser($userId));
    }

    /**
     * Returns the boolean value of a feature flag.
     * Only returns true when the cast value is exactly (bool) true.
     * Returns $fallback when the feature is absent.
     */
    public static function isEnabled(int $userId, string $featureKey, bool $fallback = false): bool
    {
        $value = self::getValue($userId, $featureKey);

        if ($value === null) {
            return $fallback;
        }

        return $value === true;
    }

    /**
     * Returns the fully-merged feature map for a user.
     *
     * Steps:
     *   1. Find the user's active subscription → fetch its plan_features as the base.
     *   2. Fetch active, non-expired user_feature_overrides and overlay them.
     *
     * Result is cached for the lifetime of the request.
     */
    public static function getAllForUser(int $userId): array
    {
        if (isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }

        $pdo      = Database::get();
        $features = [];

        // Step 1: active subscription
        $stmt = $pdo->prepare("
            SELECT plan_id
            FROM   user_subscriptions
            WHERE  user_id = ?
              AND  status  = 'active'
            ORDER BY started_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        // Step 2: plan features (base layer)
        if ($sub) {
            $stmt = $pdo->prepare("
                SELECT feature_key, feature_value, value_type
                FROM   plan_features
                WHERE  plan_id = ?
            ");
            $stmt->execute([$sub['plan_id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $features[$row['feature_key']] = self::castValue($row['feature_value'], $row['value_type']);
            }
        }

        // Step 3: user overrides (overlay — these win over plan features)
        $stmt = $pdo->prepare("
            SELECT feature_key, feature_value, value_type
            FROM   user_feature_overrides
            WHERE  user_id = ?
              AND  (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $features[$row['feature_key']] = self::castValue($row['feature_value'], $row['value_type']);
        }

        self::$cache[$userId] = $features;
        return $features;
    }

    /**
     * Drop the cached entitlement map for a user.
     * Call this after updating a subscription or override mid-request.
     */
    public static function clearCache(int $userId): void
    {
        unset(self::$cache[$userId]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Cast a raw string value to the declared PHP type.
     *
     * CONTRACT: bool-typed rows in plan_features and user_feature_overrides
     * must store ONLY the literal strings "true" or "false". This is enforced
     * by PlanFeaturesSeeder and any future admin tooling. Values like "1", "0",
     * "yes", or "on" are NOT supported and will cast to false.
     *
     * Strict matching is deliberate — loose truthiness would silently accept
     * malformed data and make debugging harder.
     */
    private static function castValue(string $raw, string $type): mixed
    {
        return match ($type) {
            'int'    => (int) $raw,
            'bool'   => $raw === 'true',   // "false" → false, anything else → false
            'string' => $raw,
            default  => $raw,
        };
    }
}
