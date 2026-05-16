<?php
declare(strict_types=1);

class PricingController
{
    public function index(array $params = []): void
    {
        $pdo = Database::get();

        $stmt = $pdo->query("
            SELECT * FROM plans
            WHERE is_public = 1 AND is_active = 1 AND is_legacy = 0
            ORDER BY sort_order ASC, id ASC
        ");
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $features = $this->loadFeaturesByPlan(array_column($plans, 'id'), $pdo);

        $currentUser   = AuthService::currentUser();
        $currentPlanId = null;
        $pendingPlanIds = [];

        if ($currentUser) {
            $userId = (int) $currentUser['id'];

            $stmt = $pdo->prepare("
                SELECT plan_id, billing_provider, provider_subscription_id
                FROM   user_subscriptions
                WHERE  user_id = ? AND status = 'active'
                ORDER  BY started_at DESC, id DESC
                LIMIT  1
            ");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $currentPlanId = $row ? (int) $row['plan_id'] : null;
            $currentSubscriptionIsStripeBacked = $row !== false
                && ($row['billing_provider'] ?? '') === 'stripe'
                && !empty($row['provider_subscription_id']);

            $stmt = $pdo->prepare("
                SELECT requested_plan_id FROM subscription_change_requests
                WHERE  user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$userId]);
            $pendingPlanIds = array_map('intval',
                array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'requested_plan_id')
            );
        }

        $stripeEnabled = StripeService::isEnabled();
        $stripePricesByPlan = [];
        if ($stripeEnabled && !empty($plans)) {
            $planIds      = array_column($plans, 'id');
            $placeholders = implode(',', array_fill(0, count($planIds), '?'));
            $stripeMode = StripeService::mode();
            $modeParams = $planIds;
            $modeParams[] = $stripeMode;
            $stmt = $pdo->prepare(
                "SELECT plan_id, billing_cycle
                   FROM plan_billing_prices
                  WHERE plan_id IN ({$placeholders})
                    AND provider = 'stripe'
                    AND provider_mode = ?
                    AND is_active = 1"
            );
            $stmt->execute($modeParams);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $stripePricesByPlan[(int) $row['plan_id']][$row['billing_cycle']] = true;
            }
        }

        $currentSubscriptionIsStripeBacked ??= false;

        View::render('pricing/index', [
            'pageTitle'          => 'Pricing — f29.us Dynamic QR',
            'plans'              => $plans,
            'features'           => $features,
            'currentUser'        => $currentUser,
            'currentPlanId'      => $currentPlanId,
            'pendingPlanIds'     => $pendingPlanIds,
            'stripeEnabled'      => $stripeEnabled,
            'stripePricesByPlan' => $stripePricesByPlan,
            'currentSubscriptionIsStripeBacked' => $currentSubscriptionIsStripeBacked,
        ]);
    }

    private function loadFeaturesByPlan(array $planIds, PDO $pdo): array
    {
        if (empty($planIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($planIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT plan_id, feature_key, feature_value, value_type
             FROM   plan_features
             WHERE  plan_id IN ($placeholders)
             ORDER  BY plan_id, feature_key ASC"
        );
        $stmt->execute($planIds);
        $features = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $features[(int) $row['plan_id']][$row['feature_key']] = $row;
        }
        return $features;
    }
}
