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
     *   2. Active user_subscriptions → plan_features (billing-status gated)
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
     *   1. Find the most relevant subscription row:
     *      - active subscriptions first
     *      - if none, the most recent Stripe-canceled subscription whose current_period_end
     *        is still in the future (access retained until period ends)
     *   2. Apply billing-status gating to determine the effective plan.
     *      - not_applicable / manual / active / trialing / past_due → subscribed plan
     *      - canceled with future current_period_end → subscribed plan
     *      - canceled with past/null period end → Free plan
     *      - unpaid / incomplete → Free plan
     *      - no qualifying subscription → Free plan
     *   3. Load plan_features for the effective plan as the base.
     *   4. Overlay active, non-expired user_feature_overrides (always win).
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

        // Step 1: find the most relevant subscription with billing context.
        // Active rows take priority; canceled Stripe rows with a future period_end are
        // included so the "canceled + future period" access rule can fire.
        $stmt = $pdo->prepare("
            SELECT plan_id, billing_status, current_period_end
              FROM user_subscriptions
             WHERE user_id = ?
               AND (
                     status = 'active'
                     OR (
                           status          = 'canceled'
                           AND billing_provider = 'stripe'
                           AND billing_status   = 'canceled'
                           AND current_period_end > NOW()
                        )
                   )
             ORDER BY CASE WHEN status = 'active' THEN 0 ELSE 1 END ASC,
                      started_at DESC, id DESC
             LIMIT 1
        ");
        $stmt->execute([$userId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        // Step 2: determine effective plan via billing-status gating
        $effectivePlanId = $sub
            ? self::effectivePlanId($sub, $pdo)
            : self::freePlanId($pdo);

        // Step 3: load plan features (base layer)
        if ($effectivePlanId !== null) {
            $stmt = $pdo->prepare("
                SELECT feature_key, feature_value, value_type
                  FROM plan_features
                 WHERE plan_id = ?
            ");
            $stmt->execute([$effectivePlanId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $features[$row['feature_key']] = self::castValue($row['feature_value'], $row['value_type']);
            }
        }

        // Step 4: user overrides (overlay — always win over plan features)
        $stmt = $pdo->prepare("
            SELECT feature_key, feature_value, value_type
              FROM user_feature_overrides
             WHERE user_id = ?
               AND (expires_at IS NULL OR expires_at > NOW())
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
     * Return the plan ID that should actually be used for entitlements,
     * taking billing_status into account.
     */
    private static function effectivePlanId(array $sub, PDO $pdo): ?int
    {
        $billingStatus = $sub['billing_status'] ?? 'not_applicable';
        $planId        = (int) $sub['plan_id'];

        // Full access: use the subscribed plan directly
        if (in_array($billingStatus, ['not_applicable', 'manual', 'active', 'trialing', 'past_due'], true)) {
            return $planId;
        }

        // Canceled: keep paid access until period end
        if ($billingStatus === 'canceled') {
            $end = $sub['current_period_end'] ?? null;
            if ($end !== null && strtotime($end) > time()) {
                return $planId;
            }
            return self::freePlanId($pdo);
        }

        // unpaid / incomplete / unknown → Free immediately
        return self::freePlanId($pdo);
    }

    /**
     * Return the ID of the active Free plan, cached for the request lifetime.
     * Returns null only if no free_v1 plan exists in the database.
     */
    private static function freePlanId(PDO $pdo): ?int
    {
        static $cached = false;
        if ($cached === false) {
            $stmt = $pdo->prepare(
                "SELECT id FROM plans WHERE internal_name = 'free_v1' AND is_active = 1 LIMIT 1"
            );
            $stmt->execute();
            $id     = $stmt->fetchColumn();
            $cached = $id !== false ? (int) $id : null;
        }
        return $cached;
    }

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
