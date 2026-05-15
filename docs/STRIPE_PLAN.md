# Stripe Integration Plan — f29.us Dynamic QR

This document defines the Stripe checkout and subscription billing architecture for f29.us.
It is a planning document. No live Stripe code has been implemented yet.

---

## Current Billing Groundwork (Already Shipped)

The following schema and features are already in place:

| Item | Location |
|------|----------|
| `user_subscriptions.billing_provider` | migration 018 |
| `user_subscriptions.provider_customer_id` | migration 018 |
| `user_subscriptions.provider_subscription_id` | migration 018 |
| `user_subscriptions.billing_status` ENUM | migration 018 |
| `user_subscriptions.current_period_start/end` | migration 018 |
| `user_subscriptions.trial_ends_at` | migration 018 |
| `user_subscriptions.cancel_at_period_end` | migration 018 |
| `plan_billing_prices` table | migration 019 |
| Admin billing price mapping UI | `PlanController` |
| `EntitlementService` | gating |
| `NotificationService` | transactional email |
| `/admin/ops` + Send Test Email | `OpsController` |

The `billing_status` ENUM values are:
`not_applicable`, `manual`, `trialing`, `active`, `past_due`, `canceled`, `unpaid`, `incomplete`

---

## Architecture Decisions

### 1. Checkout Mode

Use **Stripe-hosted Checkout** with `mode = subscription`.

- Server creates a new Checkout Session per payment attempt.
- Session uses `provider_price_id` from `plan_billing_prices` for the selected plan and billing cycle.
- User is redirected to Stripe-hosted checkout page. No card UI is rendered on f29.us.
- On completion, Stripe fires webhooks. Access is granted via webhook, not the browser return URL.
- Success/cancel URLs return the user to the app after checkout.

### 2. Stripe Customer ID — Recommended Migration

**Current schema** stores `provider_customer_id` on `user_subscriptions`.
**Problem:** A Stripe customer is a user-level entity. A user who upgrades, downgrades, or re-subscribes would create a new subscription but should reuse the same Stripe customer.

**Decision: Add `stripe_customer_id` to `users` table (Phase 35 migration).**

```sql
-- Migration 027_add_stripe_customer_id_to_users.php
ALTER TABLE users
    ADD COLUMN stripe_customer_id VARCHAR(255) NULL
        AFTER timezone,
    ADD INDEX idx_users_stripe_customer (stripe_customer_id);
```

`user_subscriptions.provider_customer_id` is retained for reference and webhook correlation.
`users.stripe_customer_id` is the authoritative lookup for create-or-retrieve customer logic.

### 3. Checkout Session Tracking Table

Implement `stripe_checkout_sessions` in Phase 36.

```sql
-- Migration: 028_create_stripe_checkout_sessions.php
CREATE TABLE stripe_checkout_sessions (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    plan_id                 BIGINT UNSIGNED NOT NULL,
    plan_billing_price_id   BIGINT UNSIGNED NOT NULL,
    stripe_session_id       VARCHAR(255)    NOT NULL,
    stripe_customer_id      VARCHAR(255)    NULL,
    status                  ENUM('pending','completed','expired','canceled')
                                NOT NULL DEFAULT 'pending',
    created_at              DATETIME        NOT NULL,
    completed_at            DATETIME        NULL,

    UNIQUE KEY uq_stripe_session_id (stripe_session_id),
    INDEX idx_scs_user_id (user_id),
    CONSTRAINT fk_scs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_scs_plan FOREIGN KEY (plan_id) REFERENCES plans(id),
    CONSTRAINT fk_scs_price FOREIGN KEY (plan_billing_price_id)
        REFERENCES plan_billing_prices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Purpose: idempotent webhook processing, auditable payment attempts, recovery from browser
abandonment without losing context.

### 4. Webhook Event Storage Table

Implement `stripe_webhook_events` in Phase 37.

```sql
-- Migration: 029_create_stripe_webhook_events.php
CREATE TABLE stripe_webhook_events (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    stripe_event_id   VARCHAR(255)    NOT NULL,
    event_type        VARCHAR(100)    NOT NULL,
    processing_status ENUM('received','processed','failed','ignored')
                          NOT NULL DEFAULT 'received',
    error_message     TEXT            NULL,
    processed_at      DATETIME        NULL,
    created_at        DATETIME        NOT NULL,

    UNIQUE KEY uq_stripe_event_id (stripe_event_id),
    INDEX idx_swe_event_type (event_type),
    INDEX idx_swe_status (processing_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Purpose: idempotency (skip already-processed events), audit trail, safe retry of failed events,
debugging webhook delivery order issues.

**Requirement:** Webhook handlers must be idempotent and must tolerate out-of-order delivery.
Check `stripe_webhook_events` for `stripe_event_id` before processing any event.

---

## Required Environment Variables

Add to `.env` and `.env.example`:

```env
# ── Stripe ────────────────────────────────────────────────────────────────────
STRIPE_ENABLED=false
STRIPE_MODE=test
STRIPE_SECRET_KEY=
STRIPE_PUBLISHABLE_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_SUCCESS_URL=https://f29.us/account/subscription?checkout=success
STRIPE_CANCEL_URL=https://f29.us/account/subscription?checkout=canceled
STRIPE_CURRENCY=usd
```

**Security:** `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` must **never** be displayed
in `/admin/ops`, error logs, or any browser output. Display only presence (configured/not set).
`STRIPE_PUBLISHABLE_KEY` and `STRIPE_MODE` are safe to show in ops.

---

## Webhook Events

### Required (Phase 37–38)

| Event | Action |
|-------|--------|
| `checkout.session.completed` | Mark checkout session completed; activate local subscription |
| `checkout.session.expired` | Mark checkout session expired; no access change |
| `customer.subscription.created` | Record provider_subscription_id; sync billing status |
| `customer.subscription.updated` | Sync plan, billing_status, period dates, cancel_at_period_end |
| `customer.subscription.deleted` | Mark subscription canceled; degrade to Free |
| `invoice.payment_succeeded` | Update current_period_start/end; clear past_due |
| `invoice.payment_failed` | Set billing_status to past_due or unpaid |

### Optional / Later

| Event | Notes |
|-------|-------|
| `customer.subscription.trial_will_end` | Send warning email |
| `invoice.finalization_failed` | Log; notify admin |
| `payment_method.attached` | No local action required initially |

### Webhook Signature Verification

The webhook endpoint must:
1. Read the raw request body **before** any JSON parsing.
2. Read the `Stripe-Signature` header.
3. Verify using `STRIPE_WEBHOOK_SECRET` via the Stripe SDK's signature verification.
4. Return HTTP 400 immediately if signature verification fails.
5. Return HTTP 200 after recording the event, even if processing is deferred.

```
POST /stripe/webhook   (Phase 37)
```

---

## Local Billing Status Mapping

| Stripe subscription status | Local `billing_status` | Access level |
|---------------------------|------------------------|--------------|
| `trialing` | `trialing` | Full paid access |
| `active` | `active` | Full paid access |
| `past_due` | `past_due` | Keep current; show banner |
| `canceled` | `canceled` | Degrade to Free at period end |
| `unpaid` | `unpaid` | Degrade to Free immediately |
| `incomplete` | `incomplete` | Do not grant paid features |
| `incomplete_expired` | `canceled` | Treat as canceled |
| `paused` | `past_due` | Treat as past_due initially; revisit |

**Note:** `incomplete` means the initial payment failed and Stripe is retrying. Do not grant
paid access until `customer.subscription.updated` fires with `active` status.

---

## Entitlement / Access Gating (Phase 38)

`EntitlementService` currently uses the plan's feature rows regardless of `billing_status`.
After Phase 38, `billing_status` gates access.

### First-version gating rules

| `billing_status` | Entitlement behavior |
|-----------------|----------------------|
| `not_applicable` | Use plan features as-is (Free or manual legacy) |
| `manual` | Full plan access (admin-assigned) |
| `active` | Full plan access |
| `trialing` | Full plan access |
| `past_due` | Keep current paid plan features; show account-level warning banner |
| `canceled` | Use plan features until `current_period_end`; then fall back to Free |
| `unpaid` | Fall back to Free entitlements immediately |
| `incomplete` | Fall back to Free entitlements; subscription not confirmed |

`past_due` does **not** immediately degrade access. A short grace period prevents penalizing users
for transient payment failures. After a configurable grace period (not implemented in Phase 38),
access degrades to Free.

### Where gating is enforced

`EntitlementService::isEnabled()` and `::getValue()` are the enforcement point.
These methods currently query `plan_features`. In Phase 38 they also check `billing_status`.
All existing call sites inherit the new behavior automatically.

---

## Subscription Switching (First Version)

| Transition | Mechanism |
|-----------|-----------|
| Free → Paid | Stripe Checkout |
| Manual/admin → Paid | Stripe Checkout allowed |
| Paid → Free | Cancel at period end via Stripe API |
| Paid → Higher paid | Defer to Phase 39+ |
| Paid → Lower paid | Defer to Phase 39+ |
| Paid → Manual/admin-assigned | Admin only |

First implementation handles only: new paid checkout from Free/manual, and cancel-at-period-end.
Paid-to-paid switching deferred.

---

## Cancellation Flow

1. User clicks "Cancel subscription" on `/account/subscription`.
2. App calls Stripe API: `stripe.subscriptions.update(sub_id, {cancel_at_period_end: true})`.
3. App sets `user_subscriptions.cancel_at_period_end = 1` locally (optimistic update).
4. Webhook `customer.subscription.updated` confirms and sets `cancel_at_period_end = 1` plus
   the `current_period_end` timestamp.
5. User retains full access until `current_period_end`.
6. Webhook `customer.subscription.deleted` fires at period end.
7. App sets `billing_status = 'canceled'`, degrades to Free entitlements.
8. If Stripe is ever slow: entitlement check falls back to comparing `current_period_end` to now.

---

## Account Subscription Page Changes (Phase 36/38)

After Stripe is live, the subscription page should reflect:

| State | UI |
|-------|----|
| Free plan | "Subscribe" button per paid plan |
| Active paid plan (Stripe) | Plan name, billing cycle, next billing date |
| `cancel_at_period_end = 1` | "Cancels on [date]" warning |
| `past_due` | Warning banner: payment failed, update payment method |
| `incomplete` | Warning: subscription not confirmed |
| Manual/admin-assigned | No billing UI shown; plan badge only |

"Request Review" flow is retired for paid plans once Stripe checkout is live. Admin/manual
assignment remains for complimentary and grandfathered access.

---

## Admin Ops Stripe Section (Phase 35)

Add a Stripe section to `/admin/ops`:

| Check | Display |
|-------|---------|
| `STRIPE_ENABLED` | enabled / disabled |
| `STRIPE_MODE` | test / live |
| `STRIPE_SECRET_KEY` | configured / not set (never show value) |
| `STRIPE_PUBLISHABLE_KEY` | configured / not set (value safe to show) |
| `STRIPE_WEBHOOK_SECRET` | configured / not set (never show value) |
| Active `plan_billing_prices` | count per provider |
| Paid plans missing active Stripe prices | list plan names |
| Latest webhook processed | timestamp from `stripe_webhook_events` |
| Failed webhooks (last 24 h) | count from `stripe_webhook_events` |

---

## Phased Implementation Order

### Phase 35 — SDK, Config, Ops Readiness
- Install `stripe/stripe-php` via Composer
- Add env vars to `.env.example`
- Create `StripeService` skeleton (API key init only)
- Add Stripe section to `/admin/ops`
- Add migration 027: `stripe_customer_id` on `users`
- No checkout, no webhooks

### Phase 36 — Checkout Session Creation
- Add migration 028: `stripe_checkout_sessions`
- Create `POST /account/subscription/checkout` controller action
- Create Checkout Session via `StripeService`
- Create/retrieve Stripe customer and store `stripe_customer_id`
- Redirect user to Stripe-hosted checkout
- Handle `GET /account/subscription?checkout=success` and `?checkout=canceled` return pages
- Update subscription page buttons: "Subscribe" replaces "Request Review" for paid plans

### Phase 37 — Webhook Endpoint
- Add migration 029: `stripe_webhook_events`
- Create `POST /stripe/webhook` route and controller
- Implement signature verification (raw body → verify → then parse)
- Handle `checkout.session.completed`: mark session, activate subscription
- Handle `checkout.session.expired`: mark session expired
- Return HTTP 200 for all valid events; HTTP 400 for signature failure

### Phase 38 — Subscription Lifecycle
- Handle `customer.subscription.updated` and `customer.subscription.deleted`
- Handle `invoice.payment_succeeded` and `invoice.payment_failed`
- Implement cancel-at-period-end flow (account page + Stripe API call)
- Update `EntitlementService` to gate on `billing_status`
- Add past_due banner to dashboard/account pages
- Send notification emails for payment failure, cancellation, access expiry

### Phase 39 — Polish, QA, Test-Mode Launch
- End-to-end test-mode QA checklist
- Verify all webhook event paths with Stripe CLI (`stripe listen`)
- Verify idempotency (replay events)
- Verify cancellation flow and period-end access expiry
- Verify entitlement downgrade paths
- Verify ops page shows correct Stripe status
- Switch `STRIPE_MODE` to `live` when checklist passes

---

## Open Decisions

1. **Grace period duration for `past_due`** — How many days before access degrades? Phase 38 does
   not implement the timer; access is kept during `past_due` and only degraded on `unpaid`.
   A grace-period timer should be decided before launch.

2. **Paid-to-paid plan switching** — Deferred to Phase 39+. Stripe supports proration; local
   subscription row update strategy needs definition.

3. **Billing portal** — Stripe Customer Portal can handle payment method updates, invoice history.
   Not planned yet. Would replace any self-built payment management UI.

4. **`billing_cycle` ENUM expansion** — Currently `monthly, yearly, manual, free`. No changes
   needed for Phase 35–39, but verify `plan_billing_prices.billing_cycle` covers all intended
   Stripe price intervals.

5. **Trial support** — `trial_ends_at` column exists but no trial creation logic is planned.
   If trials are introduced, `checkout.session.completed` with `trialing` status needs special handling.

6. **Idempotency key for checkout session creation** — Stripe checkout sessions are already
   idempotent by session ID. Decide whether to deduplicate pending sessions for the same user/plan
   within a short window, or always create a new one.

7. **Currency** — `plan_billing_prices.currency_code` exists. Confirm all prices use `USD` before
   launch, or add currency selection logic.

---

## Related Files

| File | Purpose |
|------|---------|
| `database/migrations/018_*.php` | `billing_status`, provider ID columns |
| `database/migrations/019_*.php` | `plan_billing_prices` table |
| `app/Services/EntitlementService.php` | Access gating (will be extended in Phase 38) |
| `app/Services/NotificationService.php` | Transactional email |
| `app/Services/MailerService.php` | SMTP delivery |
| `app/Controllers/AccountController.php` | Subscription page + change flow |
| `app/Controllers/OpsController.php` | Ops page (Stripe section in Phase 35) |
| `app/Controllers/PlanController.php` | Admin billing price mapping |
| `app/Views/account/subscription.php` | Subscription UI (changes in Phase 36/38) |
| `app/Views/admin/ops.php` | Ops view (Stripe section in Phase 35) |
| `docs/QA_CHECKLIST.md` | Checklist (Stripe section to be added in Phase 39) |
