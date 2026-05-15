<?php
declare(strict_types=1);

class StripeWebhookService
{
    // ── Entry point ───────────────────────────────────────────────────────────

    /**
     * Route a verified Stripe event to the appropriate handler.
     * Each event is marked exactly once: processed, ignored, or failed.
     * Always returns normally — callers should send HTTP 200 after this.
     */
    public static function handleEvent(\Stripe\Event $event): void
    {
        $eventId   = $event->id;
        $eventType = $event->type;

        if (!self::recordReceived($eventId, $eventType)) {
            // Already recorded — idempotent skip
            return;
        }

        try {
            // Each handler returns 'processed' or 'ignored'; dispatcher marks once.
            $result = match ($eventType) {
                'checkout.session.completed' => self::handleCheckoutCompleted($event->data->object, $eventId),
                'checkout.session.expired'   => self::handleCheckoutExpired($event->data->object, $eventId),
                default                      => 'ignored',
            };

            if ($result === 'processed') {
                self::markProcessed($eventId);
            } else {
                self::markIgnored($eventId);
            }
        } catch (Throwable $e) {
            error_log(
                'StripeWebhook error [' . $eventId . '/' . $eventType . ']: '
                . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine()
            );
            self::markFailed($eventId, $e->getMessage());
        }
    }

    // ── Event recording ───────────────────────────────────────────────────────

    /**
     * Insert the event into stripe_webhook_events with status 'received'.
     * Returns false when the stripe_event_id already exists (idempotent duplicate).
     */
    public static function recordReceived(string $eventId, string $eventType): bool
    {
        $pdo = Database::get();
        try {
            $pdo->prepare("
                INSERT INTO stripe_webhook_events
                    (stripe_event_id, event_type, processing_status, created_at)
                VALUES (?, ?, 'received', ?)
            ")->execute([$eventId, $eventType, gmdate('Y-m-d H:i:s')]);
            return true;
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                // Duplicate stripe_event_id — already recorded
                return false;
            }
            throw $e;
        }
    }

    public static function markProcessed(string $eventId): void
    {
        Database::get()->prepare("
            UPDATE stripe_webhook_events
               SET processing_status = 'processed', processed_at = ?
             WHERE stripe_event_id = ?
        ")->execute([gmdate('Y-m-d H:i:s'), $eventId]);
    }

    public static function markFailed(string $eventId, string $error): void
    {
        Database::get()->prepare("
            UPDATE stripe_webhook_events
               SET processing_status = 'failed', error_message = ?, processed_at = ?
             WHERE stripe_event_id = ?
        ")->execute([mb_substr($error, 0, 1000), gmdate('Y-m-d H:i:s'), $eventId]);
    }

    public static function markIgnored(string $eventId): void
    {
        Database::get()->prepare("
            UPDATE stripe_webhook_events
               SET processing_status = 'ignored', processed_at = ?
             WHERE stripe_event_id = ?
        ")->execute([gmdate('Y-m-d H:i:s'), $eventId]);
    }

    // ── checkout.session.completed ────────────────────────────────────────────

    /**
     * Returns 'processed' or 'ignored'. Never calls mark* methods directly.
     */
    private static function handleCheckoutCompleted(object $session, string $eventId): string
    {
        if (($session->mode ?? '') !== 'subscription') {
            return 'ignored';
        }

        $stripeSessionId = $session->id ?? '';
        if ($stripeSessionId === '') {
            throw new RuntimeException('checkout.session.completed missing session id.');
        }

        $stripeSubscriptionId = $session->subscription ?? '';
        if ($stripeSubscriptionId === '') {
            throw new RuntimeException('checkout.session.completed missing subscription id.');
        }

        $pdo = Database::get();

        // ── Early-exit check (no row lock yet) ────────────────────────────────
        $stmt = $pdo->prepare(
            "SELECT status FROM stripe_checkout_sessions WHERE stripe_session_id = ? LIMIT 1"
        );
        $stmt->execute([$stripeSessionId]);
        $earlyStatus = $stmt->fetchColumn();

        if ($earlyStatus === false) {
            // Not found — session not created by this system
            return 'ignored';
        }
        if ($earlyStatus === 'completed') {
            // Already activated — idempotent, no duplicate subscription
            return 'processed';
        }
        if ($earlyStatus !== 'pending') {
            // expired or canceled — nothing to activate
            return 'ignored';
        }

        // ── Retrieve Stripe subscription before opening transaction ───────────
        $stripeSub    = StripeService::retrieveSubscription($stripeSubscriptionId);
        $stripeStatus = $stripeSub->status ?? '';

        if (!in_array($stripeStatus, ['active', 'trialing'], true)) {
            throw new RuntimeException(
                "Stripe subscription {$stripeSubscriptionId} has non-activatable status: {$stripeStatus}."
            );
        }

        $billingStatus      = self::mapBillingStatus($stripeStatus);
        $stripeCustomerId   = (string) ($session->customer ?? '');
        $currentPeriodStart = self::tsToDatetime($stripeSub->current_period_start ?? null);
        $currentPeriodEnd   = self::tsToDatetime($stripeSub->current_period_end   ?? null);
        $trialEndsAt        = self::tsToDatetime(
            $stripeStatus === 'trialing' ? ($stripeSub->trial_end ?? null) : null
        );
        $now = gmdate('Y-m-d H:i:s');

        // ── Transaction: lock row, verify status, activate ────────────────────
        $newSubId = 0;
        $userId   = 0;
        $planId   = 0;

        $pdo->beginTransaction();
        try {
            // Lock the checkout session row so concurrent webhooks cannot race
            $stmt = $pdo->prepare("
                SELECT scs.*, pbp.billing_cycle
                  FROM stripe_checkout_sessions scs
                  JOIN plan_billing_prices pbp ON pbp.id = scs.plan_billing_price_id
                 WHERE scs.stripe_session_id = ?
                 LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$stripeSessionId]);
            $localSession = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$localSession || $localSession['status'] !== 'pending') {
                // Concurrent webhook already activated this checkout
                $pdo->rollBack();
                return 'processed';
            }

            $userId       = (int) $localSession['user_id'];
            $planId       = (int) $localSession['plan_id'];
            $billingCycle = $localSession['billing_cycle'];

            // Fall back to local row's customer ID if Stripe event has none
            if ($stripeCustomerId === '') {
                $stripeCustomerId = (string) ($localSession['stripe_customer_id'] ?? '');
            }

            // Cancel any existing active subscriptions for this user
            $pdo->prepare("
                UPDATE user_subscriptions
                   SET status = 'canceled', canceled_at = ?, updated_at = ?
                 WHERE user_id = ? AND status = 'active'
            ")->execute([$now, $now, $userId]);

            // Create the new Stripe-backed subscription
            $pdo->prepare("
                INSERT INTO user_subscriptions
                    (user_id, plan_id, status, billing_cycle,
                     billing_provider, provider_customer_id, provider_subscription_id,
                     billing_status, current_period_start, current_period_end,
                     trial_ends_at, cancel_at_period_end,
                     started_at, created_at, updated_at)
                VALUES (?, ?, 'active', ?,
                        'stripe', ?, ?,
                        ?, ?, ?,
                        ?, 0,
                        ?, ?, ?)
            ")->execute([
                $userId, $planId, $billingCycle,
                $stripeCustomerId, $stripeSubscriptionId,
                $billingStatus, $currentPeriodStart, $currentPeriodEnd,
                $trialEndsAt,
                $now, $now, $now,
            ]);

            $newSubId = (int) $pdo->lastInsertId();

            // Mark checkout session completed; persist customer ID
            $pdo->prepare("
                UPDATE stripe_checkout_sessions
                   SET status = 'completed', completed_at = ?, stripe_customer_id = ?
                 WHERE stripe_session_id = ?
            ")->execute([$now, $stripeCustomerId, $stripeSessionId]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        AuditLogService::log(
            $userId,
            'user_subscription',
            $newSubId,
            'stripe_checkout_completed',
            [
                'stripe_event_id'        => $eventId,
                'stripe_session_id'      => $stripeSessionId,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'plan_id'                => $planId,
                'billing_status'         => $billingStatus,
            ]
        );

        EntitlementService::clearCache($userId);
        return 'processed';
    }

    // ── checkout.session.expired ──────────────────────────────────────────────

    /**
     * Returns 'processed' or 'ignored'. Never calls mark* methods directly.
     */
    private static function handleCheckoutExpired(object $session, string $eventId): string
    {
        $stripeSessionId = $session->id ?? '';
        if ($stripeSessionId === '') {
            return 'processed';
        }

        Database::get()->prepare("
            UPDATE stripe_checkout_sessions
               SET status = 'expired'
             WHERE stripe_session_id = ? AND status = 'pending'
        ")->execute([$stripeSessionId]);

        return 'processed';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function mapBillingStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active'             => 'active',
            'trialing'           => 'trialing',
            'past_due'           => 'past_due',
            'unpaid'             => 'unpaid',
            'canceled'           => 'canceled',
            'incomplete'         => 'incomplete',
            'incomplete_expired' => 'canceled',
            default              => 'incomplete',
        };
    }

    private static function tsToDatetime(?int $ts): ?string
    {
        return $ts !== null ? gmdate('Y-m-d H:i:s', $ts) : null;
    }
}
