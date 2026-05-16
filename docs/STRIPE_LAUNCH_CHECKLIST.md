# Stripe Live-Mode Launch Checklist — f29.us Dynamic QR

Complete this checklist after the Stripe test-mode QA procedure in `docs/STRIPE_PLAN.md` (Test-Mode Validation Procedure) and `docs/QA_CHECKLIST.md` section 17 pass. Work through every section before setting `STRIPE_MODE=live`.

---

## 1. Stripe Dashboard

- [ ] Stripe account is activated for live payments (not just test mode)
- [ ] Business details and banking payout information are complete in the Stripe Dashboard
- [ ] Branding settings (logo, business name, colors) configured in Stripe Dashboard
- [ ] Email notification preferences set in Stripe Dashboard (failed payments, disputes, etc.)
- [ ] Live-mode webhook endpoint registered in Stripe Dashboard: `https://yourdomain.com/stripe/webhook`
  - Event subscriptions: `checkout.session.completed`, `checkout.session.expired`,
    `customer.subscription.updated`, `customer.subscription.deleted`,
    `invoice.payment_succeeded`, `invoice.payment_failed`
- [ ] Live-mode webhook secret copied from Stripe Dashboard → set as `STRIPE_WEBHOOK_SECRET` in production `.env`
- [ ] All paid plans have **live-mode** active Stripe prices mapped in `/admin/plans/{id}`
  - Test-mode price IDs (`price_test_...`) must be replaced with live-mode IDs (`price_...`)
- [ ] Stripe tax settings configured if required, or confirmed not applicable

---

## 2. Application Configuration

- [ ] Production `.env` updated:
  - `STRIPE_ENABLED=true`
  - `STRIPE_MODE=live`
  - `STRIPE_SECRET_KEY=sk_live_...` (live secret key — never expose or commit)
  - `STRIPE_PUBLISHABLE_KEY=pk_live_...` (live publishable key)
  - `STRIPE_WEBHOOK_SECRET=whsec_...` (from Stripe Dashboard live webhook endpoint)
  - `STRIPE_SUCCESS_URL` points to production HTTPS domain
  - `STRIPE_CANCEL_URL` points to production HTTPS domain
  - `STRIPE_CURRENCY` confirmed (e.g., `usd`)
- [ ] `APP_URL` set to production HTTPS URL (used in notification email links)
- [ ] `MAIL_ENABLED=true` and SMTP settings verified in production
- [ ] `APP_DEBUG=false` in production
- [ ] `/admin/ops` shows `STRIPE_MODE: live` (warning is expected and correct) and all keys configured

---

## 3. Functional Verification (Live Mode)

- [ ] `/admin/ops` shows Stripe SDK present, active live prices > 0, no plans missing price
- [ ] At least one real checkout test with a live card (use a small, real charge — refund immediately after via Stripe Dashboard)
- [ ] Webhook delivery confirmed via Stripe Dashboard → Webhooks → endpoint → Recent deliveries
- [ ] `user_subscriptions` row created with live Stripe IDs after real checkout
- [ ] Subscription page shows correct plan, billing status, and renewal date
- [ ] Notification email received for checkout activation (requires `MAIL_ENABLED=true`)
- [ ] Cancellation flow tested end-to-end (cancel the test subscription immediately after to avoid ongoing charges)

---

## 4. Safety Checks

- [ ] `STRIPE_SECRET_KEY` is NOT committed to version control (`.env` is in `.gitignore`)
- [ ] `STRIPE_WEBHOOK_SECRET` is NOT committed to version control
- [ ] `/admin/ops` does not display any key values (only "configured" / "not set")
- [ ] Webhook endpoint returns `400` for requests with invalid `Stripe-Signature`
- [ ] CSP header is set in server responses (`Content-Security-Policy`)
- [ ] HTTPS enforced on production — no HTTP redirects from Stripe Checkout
- [ ] `STRIPE_SUCCESS_URL` and `STRIPE_CANCEL_URL` use HTTPS production URLs
- [ ] `storage/logs/error.log` reviewed — no sensitive data logged, no Stripe exceptions during test flows

---

## 5. Business and Legal

- [ ] Terms of Service page (`/terms`) reviewed and up to date
- [ ] Privacy Policy page (`/privacy`) reviewed and up to date; mentions Stripe as payment processor
- [ ] Acceptable Use Policy (`/acceptable-use`) reviewed
- [ ] Refund policy defined and communicated (in Terms or on pricing page)
- [ ] Cancellation policy clearly stated (cancel-at-period-end; no prorated refunds if applicable)
- [ ] Currency and pricing confirmed correct for your market
- [ ] Any required legal disclosures for recurring billing in your jurisdiction are in place

---

*Complete all items above before setting `STRIPE_MODE=live`. After going live, monitor `/admin/ops` Subscription Billing State daily for the first week.*
