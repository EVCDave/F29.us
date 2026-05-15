<?php
declare(strict_types=1);

class StripeWebhookController
{
    /**
     * POST /stripe/webhook
     *
     * No CSRF or session check — Stripe sends raw HTTP with a signature header.
     * Raw body must be read before any framework parsing.
     */
    public function handle(array $params = []): void
    {
        $payload   = (string) file_get_contents('php://input');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        if ($signature === '') {
            http_response_code(400);
            echo 'Missing Stripe-Signature header.';
            return;
        }

        try {
            $event = StripeService::constructWebhookEvent($payload, $signature);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            http_response_code(400);
            echo 'Signature verification failed.';
            return;
        } catch (Throwable $e) {
            error_log('StripeWebhook constructEvent error: ' . $e->getMessage());
            http_response_code(400);
            echo 'Invalid webhook payload.';
            return;
        }

        StripeWebhookService::handleEvent($event);

        http_response_code(200);
        echo 'OK';
    }
}
