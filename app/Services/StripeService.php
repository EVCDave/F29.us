<?php
declare(strict_types=1);

class StripeService
{
    public static function isEnabled(): bool
    {
        return filter_var($_ENV['STRIPE_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

    public static function mode(): string
    {
        return $_ENV['STRIPE_MODE'] ?? 'test';
    }

    public static function currency(): string
    {
        return strtolower(trim($_ENV['STRIPE_CURRENCY'] ?? 'usd'));
    }

    public static function clientReady(): bool
    {
        return class_exists('\Stripe\StripeClient');
    }

    public static function requireEnabled(): void
    {
        if (!self::isEnabled()) {
            throw new RuntimeException('Stripe is not enabled.');
        }
    }

    // ── Customer management ───────────────────────────────────────────────────

    /**
     * Return the Stripe customer ID for a user, creating one if needed.
     * Saves the customer ID to users.stripe_customer_id on first creation.
     */
    public static function getOrCreateCustomerForUser(int $userId): string
    {
        self::requireEnabled();

        $pdo  = Database::get();
        $stmt = $pdo->prepare(
            "SELECT id, email, first_name, last_name, display_name, stripe_customer_id
               FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new RuntimeException("User {$userId} not found.");
        }

        if (!empty($user['stripe_customer_id'])) {
            return $user['stripe_customer_id'];
        }

        $stripe = self::client();

        $params = [
            'email'    => $user['email'],
            'metadata' => ['local_user_id' => (string) $userId],
        ];

        $displayName = UserService::displayName($user);
        if ($displayName !== $user['email'] && $displayName !== '') {
            $params['name'] = $displayName;
        }

        $customer   = $stripe->customers->create($params);
        $customerId = $customer->id;

        $pdo->prepare("UPDATE users SET stripe_customer_id = ?, updated_at = ? WHERE id = ?")
            ->execute([$customerId, gmdate('Y-m-d H:i:s'), $userId]);

        return $customerId;
    }

    // ── Checkout session creation ─────────────────────────────────────────────

    /**
     * Create a Stripe Checkout Session for a subscription purchase.
     * Inserts a local stripe_checkout_sessions row with status='pending'.
     *
     * Returns ['local_id', 'session_id', 'checkout_url'].
     */
    public static function createCheckoutSession(
        int $userId,
        int $planId,
        int $planBillingPriceId
    ): array {
        self::requireEnabled();

        $pdo = Database::get();

        // Validate plan
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? LIMIT 1");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$plan
            || !(bool) $plan['is_public']
            || !(bool) $plan['is_active']
            || (bool) $plan['is_legacy']
            || $plan['internal_name'] === 'free_v1'
        ) {
            throw new RuntimeException('Plan is not available for checkout.');
        }

        // Validate billing price belongs to plan, is Stripe, is active
        $stmt = $pdo->prepare(
            "SELECT * FROM plan_billing_prices
              WHERE id = ? AND plan_id = ? AND provider = 'stripe' AND is_active = 1
              LIMIT 1"
        );
        $stmt->execute([$planBillingPriceId, $planId]);
        $price = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$price) {
            throw new RuntimeException('No active Stripe price mapping found for this plan.');
        }

        $customerId = self::getOrCreateCustomerForUser($userId);
        $stripe     = self::client();

        $session = $stripe->checkout->sessions->create([
            'mode'                => 'subscription',
            'customer'            => $customerId,
            'line_items'          => [
                ['price' => $price['provider_price_id'], 'quantity' => 1],
            ],
            'success_url'         => $_ENV['STRIPE_SUCCESS_URL'] ?? '',
            'cancel_url'          => $_ENV['STRIPE_CANCEL_URL']  ?? '',
            'client_reference_id' => (string) $userId,
            'metadata'            => [
                'local_user_id'               => (string) $userId,
                'local_plan_id'               => (string) $planId,
                'local_plan_billing_price_id' => (string) $planBillingPriceId,
            ],
            'subscription_data'   => [
                'metadata' => [
                    'local_user_id' => (string) $userId,
                    'local_plan_id' => (string) $planId,
                ],
            ],
        ]);

        $now = gmdate('Y-m-d H:i:s');
        $pdo->prepare("
            INSERT INTO stripe_checkout_sessions
                (user_id, plan_id, plan_billing_price_id, stripe_session_id,
                 stripe_customer_id, status, checkout_url, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
        ")->execute([
            $userId,
            $planId,
            $planBillingPriceId,
            $session->id,
            $customerId,
            $session->url,
            $now,
        ]);

        return [
            'local_id'    => (int) $pdo->lastInsertId(),
            'session_id'  => $session->id,
            'checkout_url' => $session->url,
        ];
    }

    // ── Webhook helpers ───────────────────────────────────────────────────────

    /**
     * Verify Stripe-Signature header and parse the event payload.
     * Throws \Stripe\Exception\SignatureVerificationException on invalid signature.
     */
    public static function constructWebhookEvent(
        string $payload,
        string $signature
    ): \Stripe\Event {
        self::requireEnabled();
        return \Stripe\Webhook::constructEvent(
            $payload,
            $signature,
            $_ENV['STRIPE_WEBHOOK_SECRET'] ?? ''
        );
    }

    /**
     * Retrieve a Stripe Subscription object by its ID.
     */
    public static function retrieveSubscription(string $subscriptionId): \Stripe\Subscription
    {
        self::requireEnabled();
        return self::client()->subscriptions->retrieve($subscriptionId);
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private static function client(): \Stripe\StripeClient
    {
        return new \Stripe\StripeClient($_ENV['STRIPE_SECRET_KEY'] ?? '');
    }
}
