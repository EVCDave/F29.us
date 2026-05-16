# Stripe Integration Plan — f29.us Dynamic QR

This document defines the Stripe checkout and subscription billing architecture for f29.us.

**Current state:** Stripe SDK, configuration, `StripeService`, `users.stripe_customer_id`, Checkout
Session creation (Phase 35–36), webhook endpoint with `checkout.session.completed` activation
(Phase 37), full subscription lifecycle synchronization with billing-status gating (Phase 38),
and test-mode QA documentation with a go-live launch checklist (Phase 39) are implemented.
Switch `STRIPE_MODE` to `live` only after completing `docs/STRIPE_LAUNCH_CHECKLIST.md`.

---

## Current Implementation State

The following schema, services, and features are already in place:

| Item | Location | Phase |
|------|----------|-------|
| `user_subscriptions.billing_provider` | migration 018 | pre-35 |
| `user_subscriptions.provider_customer_id` | migration 018 | pre-35 |
| `user_subscriptions.provider_subscription_id` | migration 018 | pre-35 |
| `user_subscriptions.billing_status` ENUM | migration 018 | pre-35 |
| `user_subscriptions.current_period_start/end` | migration 018 | pre-35 |
| `user_subscriptions.trial_ends_at` | migration 018 | pre-35 |
| `user_subscriptions.cancel_at_period_end` | migration 018 | pre-35 |
| `plan_billing_prices` table | migration 019 | pre-35 |
| Admin billing price mapping UI | `PlanController` | pre-35 |
| `EntitlementService` | gating (billing_status not yet checked) | pre-35 |
| `NotificationService` | transactional email | pre-35 |
| `/admin/ops` + Send Test Email | `OpsController` | pre-35 |
| `stripe/stripe-php ^16.0` | `composer.json` | 35 |
| Stripe env vars (`STRIPE_ENABLED`, keys, URLs…) | `.env.example` | 35 |
| Config validation for Stripe vars | `ConfigValidator` | 35 |
| `StripeService` (`isEnabled`, `mode`, `currency`, `clientReady`, `requireEnabled`) | `app/Services/StripeService.php` | 35 |
| `users.stripe_customer_id` | migration 027 | 35 |
| Stripe ops readiness section | `/admin/ops` | 35 |
| `stripe_checkout_sessions` table | migration 028 | 36 |
| `StripeService::getOrCreateCustomerForUser()` | `app/Services/StripeService.php` | 36 |
| `StripeService::createCheckoutSession()` | `app/Services/StripeService.php` | 36 |
| `POST /account/subscription/checkout` | `AccountController` | 36 |
| Subscribe/Request Review button logic | subscription + pricing views | 36 |
| Checkout return messaging (`?checkout=success/canceled`) | `AccountController` + subscription view | 36 |
| `stripe_webhook_events` table | migration 029 | 37 |
| `StripeService::constructWebhookEvent()` | `app/Services/StripeService.php` | 37 |
| `StripeService::retrieveSubscription()` | `app/Services/StripeService.php` | 37 |
| `StripeWebhookService` (idempotent recording + event routing) | `app/Services/StripeWebhookService.php` | 37 |
| `checkout.session.completed` handler (subscription activation) | `StripeWebhookService` | 37 |
| `checkout.session.expired` handler | `StripeWebhookService` | 37 |
| `POST /stripe/webhook` route + controller | `StripeWebhookController`, `public/index.php` | 37 |
| Webhook stats expanded in Admin Ops | `OpsController`, `ops.php` | 37 |
| `StripeService::cancelSubscriptionAtPeriodEnd()` | `app/Services/StripeService.php` | 38 |
| `StripeService::mapSubscriptionStatus()` | `app/Services/StripeService.php` | 38 |
| `StripeService::stripeTimestampToSql()` | `app/Services/StripeService.php` | 38 |
| `customer.subscription.updated` handler | `StripeWebhookService` | 38 |
| `customer.subscription.deleted` handler | `StripeWebhookService` | 38 |
| `invoice.payment_succeeded` handler | `StripeWebhookService` | 38 |
| `invoice.payment_failed` handler | `StripeWebhookService` | 38 |
| `POST /account/subscription/cancel-stripe` | `AccountController`, `public/index.php` | 38 |
| `EntitlementService` billing-status gating | `app/Services/EntitlementService.php` | 38 |
| `BillingStatusService` (banners, access checks) | `app/Services/BillingStatusService.php` | 38 |
| Billing banners on subscription + dashboard pages | `subscription.php`, `dashboard.php` | 38 |
| Cancel subscription button on subscription page | `subscription.php` | 38 |
| Billing status + period-end rows in subscription table | `subscription.php` | 38 |
| `NotificationService::paymentFailed()` | `app/Services/NotificationService.php` | 38 |
| `NotificationService::subscriptionCancellationScheduled()` | `app/Services/NotificationService.php` | 38 |
| `NotificationService::subscriptionCanceled()` | `app/Services/NotificationService.php` | 38 |
| Subscription billing-state counts in Admin Ops | `OpsController`, `ops.php` | 38 |

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
-- Migration: 028_create_stripe_checkout_sessions.php  ✓ IMPLEMENTED
CREATE TABLE stripe_checkout_sessions (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id                 BIGINT UNSIGNED NOT NULL,
    plan_id                 BIGINT UNSIGNED NOT NULL,
    plan_billing_price_id   BIGINT UNSIGNED NOT NULL,
    stripe_session_id       VARCHAR(255)    NOT NULL,
    stripe_customer_id      VARCHAR(255)    NULL,
    status                  ENUM('pending','completed','expired','canceled')
                                NOT NULL DEFAULT 'pending',
    checkout_url            TEXT            NULL,
    created_at              DATETIME        NOT NULL,
    completed_at            DATETIME        NULL,

    UNIQUE KEY uq_stripe_session_id (stripe_session_id),
    INDEX idx_scs_user_id (user_id),
    INDEX idx_scs_status (status),
    INDEX idx_scs_created_at (created_at),
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
    INDEX idx_swe_status (processing_status),
    INDEX idx_swe_created_at (created_at)
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
| `STRIPE_PUBLISHABLE_KEY` | configured / not set (never show value) |
| `STRIPE_WEBHOOK_SECRET` | configured / not set (never show value) |
| Active `plan_billing_prices` | count per provider |
| Paid plans missing active Stripe prices | list plan names |
| Latest webhook processed | timestamp from `stripe_webhook_events` |
| Failed webhooks (last 24 h) | count from `stripe_webhook_events` |

---

## Phased Implementation Order

### Phase 35 — SDK, Config, Ops Readiness ✓ COMPLETE
- `stripe/stripe-php ^16.0` added to `composer.json` (run `composer install` to install)
- Stripe env vars added to `.env.example` (8 vars: STRIPE_ENABLED, STRIPE_MODE, keys, URLs, currency)
- Config validation added to `ConfigValidator` — required vars fatal on startup when `STRIPE_ENABLED=true`
- `app/Services/StripeService.php` created — `isEnabled()`, `mode()`, `currency()`, `clientReady()`, `requireEnabled()`
- `StripeService` loaded in `bootstrap.php`
- Migration 027 added — `stripe_customer_id VARCHAR(255) NULL` on `users`, with index
- Stripe Configuration section added to `/admin/ops` — SDK presence, key status (no values shown), price coverage, webhook table status
- No checkout, no webhooks

### Phase 36 — Checkout Session Creation ✓ COMPLETE
- Migration 028 added — `stripe_checkout_sessions` tracking table
- `StripeService::getOrCreateCustomerForUser()` — creates/reuses Stripe customer, writes `users.stripe_customer_id`
- `StripeService::createCheckoutSession()` — validates plan + price server-side, creates Stripe Checkout Session (mode=subscription), inserts local row with status=pending, returns checkout URL
- `POST /account/subscription/checkout` added to `AccountController` — CSRF + auth + verified email + server-side plan/price lookup
- `POST /account/subscription/change` now blocks paid plans when `STRIPE_ENABLED=true` (redirects with error)
- Subscription page: "Subscribe" button (posts to /checkout) when Stripe enabled + active price exists; "Online checkout not configured" when no active price; "Request Review" when Stripe disabled
- Pricing page: same button logic; subtitle updated based on `STRIPE_ENABLED`
- Return URL handling: `?checkout=success` and `?checkout=canceled` show informational banners only
- No entitlements granted or subscription activated from browser return — webhook phase handles that

### Phase 37 — Webhook Endpoint ✓ COMPLETE
- Migration 029 added — `stripe_webhook_events` table (idempotent event recording)
- `StripeService::constructWebhookEvent()` — verifies `Stripe-Signature` header via Stripe SDK
- `StripeService::retrieveSubscription()` — retrieves live Stripe subscription object
- `StripeWebhookService` created — idempotent event recording (UNIQUE on `stripe_event_id`), event routing, mark processed/failed/ignored
- `checkout.session.completed` handler — validates mode=subscription, retrieves Stripe subscription, activates local `user_subscriptions` (transaction: cancel old, insert new with billing_status mapped), audit-logs, clears entitlement cache
- `checkout.session.expired` handler — marks local `stripe_checkout_sessions` row as expired
- `StripeWebhookController` created — reads raw body from `php://input`, verifies signature (400 on failure), calls `StripeWebhookService::handleEvent()`, returns 200
- `POST /stripe/webhook` route registered in `public/index.php` (before slug catch-all; no CSRF)
- Admin Ops webhook section expanded: total event count, latest processed timestamp, failed/ignored counts (24 h)

### Phase 38 — Subscription Lifecycle ✓ COMPLETE
- `customer.subscription.updated` handler — syncs billing_status, period dates, cancel_at_period_end; sets status=canceled if billing_status=canceled; clears entitlement cache; audit-logged
- `customer.subscription.deleted` handler — sets billing_status=canceled, status=canceled; clears cache; fires `NotificationService::subscriptionCanceled()`; audit-logged
- `invoice.payment_succeeded` handler — sets billing_status=active, updates period dates; clears cache; audit-logged
- `invoice.payment_failed` handler — sets billing_status=past_due; clears cache; fires `NotificationService::paymentFailed()`; audit-logged
- `POST /account/subscription/cancel-stripe` — CSRF + auth + verified email + active Stripe sub lookup; calls `StripeService::cancelSubscriptionAtPeriodEnd()`; optimistic local update; fires `NotificationService::subscriptionCancellationScheduled()`; audit-logged
- `EntitlementService` updated with billing-status gating: not_applicable/manual/active/trialing/past_due → subscribed plan; canceled+future period → subscribed plan; canceled+past period/unpaid/incomplete → Free plan; no active subscription → Free plan
- `BillingStatusService` created — `bannerForSubscription()`, `isStripeBacked()`, `isAccessCurrentlyPaid()`
- Billing banners displayed on subscription page and dashboard (info for cancellation scheduled/canceled+future access; error/warning for payment failure, unpaid, access ended)
- Billing status and period-end/renews-on rows added to subscription page Current Plan table
- Cancel subscription button (Stripe-backed only, conditional on active+not-already-canceling)
- Notification emails: `paymentFailed()`, `subscriptionCancellationScheduled()`, `subscriptionCanceled()`
- Admin Ops: Subscription Billing State section (active/trialing/past_due/unpaid/incomplete/cancel_soon counts)

### Phase 39 — Polish, QA, Test-Mode Launch ✓ COMPLETE
- End-to-end Stripe test-mode QA checklist added to `docs/QA_CHECKLIST.md` section 17
- Stripe CLI test procedure documented (see Test-Mode Validation Procedure below)
- Go-live launch checklist created: `docs/STRIPE_LAUNCH_CHECKLIST.md`
- Admin Ops Stripe Configuration: clarifying note added ("complete QA checklist before enabling live billing")
- Switch `STRIPE_MODE` to `live` only after `docs/STRIPE_LAUNCH_CHECKLIST.md` passes

---

## Test-Mode Validation Procedure

Use this procedure to validate the full Stripe integration in test mode before switching to live. Steps assume the Stripe CLI is installed and the local dev server is running at `http://localhost:8000`.

### Step 1 — Start the Stripe CLI listener

```sh
stripe listen --forward-to http://localhost:8000/stripe/webhook
```

Copy the webhook signing secret printed by the CLI into `STRIPE_WEBHOOK_SECRET` in `.env`.

### Step 2 — Confirm `.env` configuration

```
STRIPE_ENABLED=true
STRIPE_MODE=test
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...  ← from stripe listen output
STRIPE_SUCCESS_URL=http://localhost:8000/account/subscription?checkout=success
STRIPE_CANCEL_URL=http://localhost:8000/account/subscription?checkout=canceled
```

### Step 3 — Verify /admin/ops

Visit `/admin/ops`. Confirm all Stripe rows show green: enabled, test mode, all keys configured, SDK present, active prices > 0, no plans missing prices.

### Step 4 — Complete a test checkout

1. Log in as a user with a verified email and a Free or manual plan.
2. Visit `/pricing` or `/account/subscription` and click Subscribe.
3. On the Stripe Checkout page, enter test card `4242 4242 4242 4242`, future expiry, any CVC.
4. Confirm payment succeeds and browser returns to `/account/subscription?checkout=success`.

### Step 5 — Verify webhook activation

- Stripe CLI terminal: `checkout.session.completed` shows `200`.
- `user_subscriptions`: new `status='active'` row with `billing_provider='stripe'`, Stripe IDs populated, period dates set.
- Audit log: `action='stripe_checkout_completed'`.
- Subscription page: correct plan name, billing status = active, renewal date shown.

### Step 6 — Test idempotency

Run: `stripe events resend <evt_id>` (copy the event ID from the CLI terminal output).

Confirm: second delivery returns `200`, no duplicate `user_subscriptions` row created.

### Step 7 — Trigger invoice events

```sh
stripe trigger invoice.payment_succeeded
stripe trigger invoice.payment_failed
```

- After `payment_succeeded`: `billing_status` remains `active`, period dates updated, audit log entry.
- After `payment_failed`: `billing_status` = `past_due`, dashboard and subscription page show warning banner, notification email sent (if `MAIL_ENABLED=true`).

### Step 8 — Test cancellation flow

1. Visit `/account/subscription` as the subscribed user. Click "Cancel Subscription".
2. Confirm: info banner shown, button hidden, notification email received.
3. Trigger deletion: `stripe trigger customer.subscription.deleted`
4. Confirm: `billing_status='canceled'`, `status='canceled'`.
5. If `current_period_end` is future: user still has paid access; subscription page shows "Access Until" date.

### Step 9 — Test entitlement downgrade

After the subscription row has `status='canceled'`:

- Update `current_period_end` to a past timestamp in the DB.
- Reload a page that requires the paid plan — user should be gated to Free.
- Restore `current_period_end` to future — user regains access.

### Step 10 — Test notification emails

Requires `MAIL_ENABLED=true` and valid SMTP config in `.env`.

Confirm emails arrive for: payment failed, cancellation scheduled, subscription ended. Check that all email links use the correct `APP_URL`.

### Step 11 — Check /admin/ops billing state

After each test step, verify `/admin/ops` Subscription Billing State counts reflect the current state. Confirm warning indicators appear for past_due and unpaid rows when counts > 0.

### Step 12 — Review audit logs

Spot-check `/admin/audit-logs` for entries: `stripe_checkout_completed`, `stripe_subscription_updated`, `stripe_subscription_deleted`, `stripe_invoice_paid`, `stripe_invoice_payment_failed`, `stripe_subscription_cancel_requested`.

### Step 13 — Confirm no errors in logs

Check `storage/logs/error.log`. No Stripe-related exceptions should appear during normal flows.

### Step 14 — Complete STRIPE_LAUNCH_CHECKLIST.md

Work through `docs/STRIPE_LAUNCH_CHECKLIST.md` before changing `STRIPE_MODE=live`. The checklist covers Stripe Dashboard setup, production `.env` config, functional verification, safety checks, and business/legal requirements.

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
| `database/migrations/027_*.php` | `users.stripe_customer_id` (Phase 35) |
| `database/migrations/028_*.php` | `stripe_checkout_sessions` table (Phase 36) |
| `database/migrations/029_*.php` | `stripe_webhook_events` table (Phase 37) |
| `app/Services/StripeService.php` | Customer + checkout session creation + webhook helpers (Phase 35–37) |
| `app/Services/StripeWebhookService.php` | Webhook event recording, routing, checkout activation (Phase 37) |
| `app/Services/EntitlementService.php` | Access gating (billing_status gating added in Phase 38) |
| `app/Services/NotificationService.php` | Transactional email |
| `app/Services/MailerService.php` | SMTP delivery |
| `app/Controllers/AccountController.php` | Subscription page, change flow, checkout (Phase 36) |
| `app/Controllers/PricingController.php` | Pricing page with Stripe-aware buttons (Phase 36) |
| `app/Controllers/StripeWebhookController.php` | Webhook endpoint — signature verify, delegate to service (Phase 37) |
| `app/Controllers/OpsController.php` | Ops page (Stripe section in Phase 35; webhook stats expanded Phase 37) |
| `app/Controllers/PlanController.php` | Admin billing price mapping |
| `app/Views/account/subscription.php` | Subscription UI — Subscribe/Request Review buttons (Phase 36) |
| `app/Views/pricing/index.php` | Pricing page — Subscribe/Request Review buttons (Phase 36) |
| `app/Views/admin/ops.php` | Ops view — Stripe configuration section (Phase 35) |
| `docs/QA_CHECKLIST.md` | Manual QA checklist — Stripe sections added for Phases 35–39 |
| `docs/STRIPE_LAUNCH_CHECKLIST.md` | Go-live checklist — complete before setting `STRIPE_MODE=live` |
