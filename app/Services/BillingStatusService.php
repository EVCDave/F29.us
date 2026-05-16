<?php
declare(strict_types=1);

class BillingStatusService
{
    /**
     * Return a banner array for the given subscription row, or null if none needed.
     * Banner: ['type' => 'info'|'warning', 'message' => string]
     *
     * Priority order: most critical first.
     */
    public static function bannerForSubscription(array $sub): ?array
    {
        $billingStatus   = $sub['billing_status']     ?? 'not_applicable';
        $cancelAtEnd     = (bool) ($sub['cancel_at_period_end'] ?? false);
        $periodEnd       = $sub['current_period_end'] ?? null;
        $periodEndFmt    = $periodEnd ? date('F j, Y', strtotime($periodEnd)) : null;

        if (in_array($billingStatus, ['unpaid', 'incomplete'], true)) {
            return [
                'type'    => 'warning',
                'message' => 'Your paid subscription is not active. Your account is currently limited to Free plan features.',
            ];
        }

        if ($billingStatus === 'past_due') {
            return [
                'type'    => 'warning',
                'message' => 'We could not process your latest payment. Your paid features remain active for now, but action may be required.',
            ];
        }

        if ($billingStatus === 'canceled') {
            if ($periodEnd !== null && strtotime($periodEnd) > time()) {
                return [
                    'type'    => 'info',
                    'message' => 'Your subscription has been canceled and will end on ' . $periodEndFmt . '. You retain full access until then.',
                ];
            }
            return [
                'type'    => 'warning',
                'message' => 'Your paid subscription has ended. Your account is currently limited to Free plan features.',
            ];
        }

        if ($cancelAtEnd && $periodEndFmt !== null) {
            return [
                'type'    => 'info',
                'message' => 'Your subscription is scheduled to cancel at the end of the current billing period on ' . $periodEndFmt . '.',
            ];
        }

        return null;
    }

    /**
     * True when the subscription is managed by Stripe and has a provider subscription ID.
     */
    public static function isStripeBacked(array $sub): bool
    {
        return ($sub['billing_provider'] ?? '') === 'stripe'
            && !empty($sub['provider_subscription_id']);
    }

    /**
     * True when the user currently has access to their paid plan features.
     * past_due is included: paid features are retained during the grace window.
     */
    public static function isAccessCurrentlyPaid(array $sub): bool
    {
        $status = $sub['billing_status'] ?? 'not_applicable';

        if (in_array($status, ['not_applicable', 'manual', 'active', 'trialing', 'past_due'], true)) {
            return true;
        }

        if ($status === 'canceled') {
            $end = $sub['current_period_end'] ?? null;
            return $end !== null && strtotime($end) > time();
        }

        return false;
    }
}
