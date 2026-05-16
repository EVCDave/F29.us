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
                'checkout.session.completed'    => self::handleCheckoutCompleted($event->data->object, $eventId),
                'checkout.session.expired'      => self::handleCheckoutExpired($event->data->object, $eventId),
                'customer.subscription.updated' => self::handleSubscriptionUpdated($event->data->object, $eventId),
                'customer.subscription.deleted' => self::handleSubscriptionDeleted($event->data->object, $eventId),
                'invoice.payment_succeeded'     => self::handleInvoicePaymentSucceeded($event->data->object, $eventId),
                'invoice.payment_failed'        => self::handleInvoicePaymentFailed($event->data->object, $eventId),
                default                         => 'ignored',
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
            return 'ignored';
        }
        if ($earlyStatus === 'completed') {
            return 'processed';
        }
        if ($earlyStatus !== 'pending') {
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

        $billingStatus      = StripeService::mapSubscriptionStatus($stripeStatus);
        $stripeCustomerId   = (string) ($session->customer ?? '');
        $currentPeriodStart = StripeService::stripeTimestampToSql($stripeSub->current_period_start ?? null);
        $currentPeriodEnd   = StripeService::stripeTimestampToSql($stripeSub->current_period_end   ?? null);
        $trialEndsAt        = StripeService::stripeTimestampToSql(
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
                $pdo->rollBack();
                return 'processed';
            }

            $userId       = (int) $localSession['user_id'];
            $planId       = (int) $localSession['plan_id'];
            $billingCycle = $localSession['billing_cycle'];

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

    // ── customer.subscription.updated ────────────────────────────────────────

    private static function handleSubscriptionUpdated(object $subscription, string $eventId): string
    {
        $stripeSubId = $subscription->id ?? '';
        if ($stripeSubId === '') {
            return 'ignored';
        }

        $pdo  = Database::get();
        $stmt = $pdo->prepare("
            SELECT id, user_id, billing_status
              FROM user_subscriptions
             WHERE provider_subscription_id = ? AND billing_provider = 'stripe'
             ORDER BY id DESC
             LIMIT 1
        ");
        $stmt->execute([$stripeSubId]);
        $localSub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$localSub) {
            return 'ignored';
        }

        $localSubId       = (int) $localSub['id'];
        $userId           = (int) $localSub['user_id'];
        $oldBillingStatus = (string) $localSub['billing_status'];

        $newBillingStatus   = StripeService::mapSubscriptionStatus($subscription->status ?? '');
        $currentPeriodStart = StripeService::stripeTimestampToSql($subscription->current_period_start ?? null);
        $currentPeriodEnd   = StripeService::stripeTimestampToSql($subscription->current_period_end   ?? null);
        $trialEndsAt        = StripeService::stripeTimestampToSql($subscription->trial_end             ?? null);
        $cancelAtPeriodEnd  = (int) (bool) ($subscription->cancel_at_period_end ?? false);
        $now = gmdate('Y-m-d H:i:s');

        if ($newBillingStatus === 'canceled') {
            $pdo->prepare("
                UPDATE user_subscriptions
                   SET billing_status = ?,
                       current_period_start = ?, current_period_end = ?,
                       trial_ends_at = ?, cancel_at_period_end = 0,
                       status = 'canceled', canceled_at = ?, updated_at = ?
                 WHERE id = ?
            ")->execute([
                $newBillingStatus,
                $currentPeriodStart, $currentPeriodEnd,
                $trialEndsAt, $now, $now,
                $localSubId,
            ]);
        } else {
            $pdo->prepare("
                UPDATE user_subscriptions
                   SET billing_status = ?,
                       current_period_start = ?, current_period_end = ?,
                       trial_ends_at = ?, cancel_at_period_end = ?,
                       updated_at = ?
                 WHERE id = ?
            ")->execute([
                $newBillingStatus,
                $currentPeriodStart, $currentPeriodEnd,
                $trialEndsAt, $cancelAtPeriodEnd, $now,
                $localSubId,
            ]);
        }

        EntitlementService::clearCache($userId);

        AuditLogService::log(
            $userId,
            'user_subscription',
            $localSubId,
            'stripe_subscription_updated',
            [
                'stripe_event_id'        => $eventId,
                'stripe_subscription_id' => $stripeSubId,
                'old_billing_status'     => $oldBillingStatus,
                'new_billing_status'     => $newBillingStatus,
                'cancel_at_period_end'   => $cancelAtPeriodEnd,
                'current_period_end'     => $currentPeriodEnd,
            ]
        );

        return 'processed';
    }

    // ── customer.subscription.deleted ─────────────────────────────────────────

    private static function handleSubscriptionDeleted(object $subscription, string $eventId): string
    {
        $stripeSubId = $subscription->id ?? '';
        if ($stripeSubId === '') {
            return 'ignored';
        }

        $pdo  = Database::get();
        $stmt = $pdo->prepare("
            SELECT id, user_id
              FROM user_subscriptions
             WHERE provider_subscription_id = ? AND billing_provider = 'stripe'
             ORDER BY id DESC
             LIMIT 1
        ");
        $stmt->execute([$stripeSubId]);
        $localSub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$localSub) {
            return 'ignored';
        }

        $localSubId = (int) $localSub['id'];
        $userId     = (int) $localSub['user_id'];
        $now = gmdate('Y-m-d H:i:s');

        $pdo->prepare("
            UPDATE user_subscriptions
               SET billing_status = 'canceled', status = 'canceled',
                   canceled_at = ?, cancel_at_period_end = 0, updated_at = ?
             WHERE id = ?
        ")->execute([$now, $now, $localSubId]);

        EntitlementService::clearCache($userId);

        AuditLogService::log(
            $userId,
            'user_subscription',
            $localSubId,
            'stripe_subscription_deleted',
            [
                'stripe_event_id'        => $eventId,
                'stripe_subscription_id' => $stripeSubId,
            ]
        );

        NotificationService::subscriptionCanceled($localSubId);

        return 'processed';
    }

    // ── invoice.payment_succeeded ─────────────────────────────────────────────

    private static function handleInvoicePaymentSucceeded(object $invoice, string $eventId): string
    {
        $stripeSubId = $invoice->subscription ?? '';
        if (!is_string($stripeSubId) || $stripeSubId === '') {
            return 'ignored';
        }

        $pdo  = Database::get();
        $stmt = $pdo->prepare("
            SELECT id, user_id
              FROM user_subscriptions
             WHERE provider_subscription_id = ? AND billing_provider = 'stripe'
             ORDER BY id DESC
             LIMIT 1
        ");
        $stmt->execute([$stripeSubId]);
        $localSub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$localSub) {
            return 'ignored';
        }

        $localSubId = (int) $localSub['id'];
        $userId     = (int) $localSub['user_id'];

        // Invoice carries period_start/period_end for the billing window
        $periodStart = StripeService::stripeTimestampToSql($invoice->period_start ?? null);
        $periodEnd   = StripeService::stripeTimestampToSql($invoice->period_end   ?? null);
        $now = gmdate('Y-m-d H:i:s');

        if ($periodStart !== null && $periodEnd !== null) {
            $pdo->prepare("
                UPDATE user_subscriptions
                   SET billing_status = 'active',
                       current_period_start = ?, current_period_end = ?,
                       updated_at = ?
                 WHERE id = ?
            ")->execute([$periodStart, $periodEnd, $now, $localSubId]);
        } else {
            $pdo->prepare("
                UPDATE user_subscriptions
                   SET billing_status = 'active', updated_at = ?
                 WHERE id = ?
            ")->execute([$now, $localSubId]);
        }

        EntitlementService::clearCache($userId);

        AuditLogService::log(
            $userId,
            'user_subscription',
            $localSubId,
            'stripe_invoice_payment_succeeded',
            [
                'stripe_event_id'        => $eventId,
                'stripe_subscription_id' => $stripeSubId,
            ]
        );

        return 'processed';
    }

    // ── invoice.payment_failed ────────────────────────────────────────────────

    private static function handleInvoicePaymentFailed(object $invoice, string $eventId): string
    {
        $stripeSubId = $invoice->subscription ?? '';
        if (!is_string($stripeSubId) || $stripeSubId === '') {
            return 'ignored';
        }

        $pdo  = Database::get();
        $stmt = $pdo->prepare("
            SELECT id, user_id
              FROM user_subscriptions
             WHERE provider_subscription_id = ? AND billing_provider = 'stripe'
             ORDER BY id DESC
             LIMIT 1
        ");
        $stmt->execute([$stripeSubId]);
        $localSub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$localSub) {
            return 'ignored';
        }

        $localSubId = (int) $localSub['id'];
        $userId     = (int) $localSub['user_id'];
        $now = gmdate('Y-m-d H:i:s');

        $pdo->prepare("
            UPDATE user_subscriptions
               SET billing_status = 'past_due', updated_at = ?
             WHERE id = ?
        ")->execute([$now, $localSubId]);

        EntitlementService::clearCache($userId);

        AuditLogService::log(
            $userId,
            'user_subscription',
            $localSubId,
            'stripe_invoice_payment_failed',
            [
                'stripe_event_id'        => $eventId,
                'stripe_subscription_id' => $stripeSubId,
            ]
        );

        NotificationService::paymentFailed($localSubId);

        return 'processed';
    }
}
