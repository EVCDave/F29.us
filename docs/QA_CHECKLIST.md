# f29.us Dynamic QR — Launch QA Checklist

Manual regression checklist for pre-launch and post-deployment verification.
Work through each section top-to-bottom. Check off items as you go.

**Setup:** run against a clean local install OR a staging/production environment.
All checklist items are manual unless noted otherwise.

---

## 1. Environment / Install

- [ ] `.env` copied from `.env.example` and all required vars set (`APP_KEY`, `APP_URL`, `QR_BASE_URL`, `DB_*`)
- [ ] `APP_KEY` is a 64-character hex string (generated with `php -r "echo bin2hex(random_bytes(32));"`)
- [ ] `APP_DEBUG=false` on production; `APP_DEBUG=true` on local only
- [ ] `composer install --no-dev --optimize-autoloader` completes without errors
- [ ] `php migrate.php` runs; all migrations report `done` or `skip`; exits 0
- [ ] `php seed.php` runs; `PlansSeeder` and `PlanFeaturesSeeder` report `done`; exits 0
- [ ] `php cleanup.php` runs and exits 0
- [ ] Visiting `/` returns HTTP 200 (not a white screen or 500)
- [ ] Visiting a nonexistent path (e.g. `/nonexistent-xyz`) returns HTTP 404 page — not a crash

---

## 2. Auth / Account

### Register
- [ ] `/register` loads without error
- [ ] Submitting blank fields shows validation errors (email, password required)
- [ ] Submitting a weak password (< 8 chars) shows a validation error
- [ ] Valid registration succeeds and redirects to `/dashboard`
- [ ] User is assigned the `free_v1` plan after registration
- [ ] Duplicate email shows "already registered" error — not a 500

### Login / Logout
- [ ] `/login` loads without error
- [ ] Wrong credentials show error; no account detail leaked
- [ ] Valid credentials redirect to `/dashboard`
- [ ] Logout POST at `/logout` ends session and redirects to `/login`
- [ ] Accessing `/dashboard` after logout redirects to `/login`

### Login throttle
- [ ] 5 failed login attempts for the same email within 15 min shows lockout message
- [ ] Wait 15 min (or clear `login_attempts` row); login works again

### Suspended account
- [ ] Set `status = 'suspended'` for a user in the DB; attempt login → blocked with suspension message
- [ ] Reset to `status = 'active'`; login works again

### Account settings — email update
- [ ] `/account/settings` loads with current email pre-filled
- [ ] Submitting new email with wrong current password shows error
- [ ] Submitting same email as current shows info notice (not error)
- [ ] Submitting duplicate email (already registered) shows error — not a 500
- [ ] Valid new email + correct password updates email; flash success shown

### Account settings — password change
- [ ] Wrong current password shows error
- [ ] New password < 8 chars shows error
- [ ] New passwords not matching shows error
- [ ] New password same as current shows error
- [ ] Valid change succeeds; old password no longer works; new password works

### CSRF spot check
- [ ] Submit the login form with the CSRF token field removed (e.g. via browser dev tools) → 403

---

## 3. QR Management

### Create
- [ ] `/qr/create` loads for authenticated user
- [ ] Submitting blank name shows validation error
- [ ] Submitting invalid URL (missing scheme) shows validation error
- [ ] Submitting a destination URL on a blocked domain shows "not allowed" error
- [ ] Valid submission creates QR code and redirects to detail page
- [ ] QR code count increments in dashboard/nav

### Custom slug (entitlement-gated)
- [ ] User on Free plan: custom slug field is not available (or shows entitlement error if submitted)
- [ ] User with `can_use_custom_slug = true`: custom slug field accepted
- [ ] Custom slug matching a reserved word (e.g. `admin`, `account`, `qr`) is rejected
- [ ] Duplicate custom slug shows validation error

### List / search / filter
- [ ] `/qr` shows the user's QR codes only (no other users' codes)
- [ ] Search by name filters results
- [ ] Status filter (active/paused/archived) filters results correctly

### Edit name and destination
- [ ] `/qr/{id}/edit` loads
- [ ] Name update saves and shows on detail
- [ ] Destination update saves; old destination appears in history list on detail page
- [ ] Destination update to a blocked domain is rejected

### Destination history / restore
- [ ] After two destination changes, detail page shows history list with two entries
- [ ] Restoring a previous destination sets it as current
- [ ] Restoring a destination that is now blocked is rejected

### Pause / Resume
- [ ] Pause button sets status to `paused`; detail page shows paused state
- [ ] Visiting the short URL while paused shows the unavailable page (not a redirect)
- [ ] Resume button sets status back to `active`; short URL redirects again

### Archive / Restore and QR quota

**Quota policy:** active, paused, and disabled QR codes count toward `max_qr_codes`. Archived QR codes do **not** count.

- [ ] Archive button sets status to `archived`; short URL shows unavailable page
- [ ] After archiving, active QR usage count on dashboard and subscription page drops by 1
- [ ] Archived QR detail page shows info note: "not redirecting and does not count against your active QR code limit"
- [ ] QR list filtered to Archived shows note: "Archived QR codes do not count against your active QR limit"

**Restore at-capacity:**
- [ ] With user at max active QR codes (e.g. 5 of 5): Restore button on archived QR detail is disabled (not a form button)
- [ ] Attempting to POST `/qr/{id}/restore` directly while at capacity (e.g. via curl) redirects back to detail with error flash: "active QR code limit has been reached"
- [ ] After archiving a different QR (dropping to 4 of 5), Restore button is enabled; restore succeeds; QR becomes active

**Restore succeeds:**
- [ ] Restoring an archived QR sets status back to `active`; short URL redirects again
- [ ] Active usage count increments after successful restore

**History and analytics preserved through archive/restore:**
- [ ] Archived QR detail page: analytics page still loads; scan history visible
- [ ] Archived QR detail page: destination history still visible
- [ ] Audit log shows archive and restore events

**Disabled QR codes still count:**
- [ ] A disabled QR (admin-disabled) is counted toward the active quota — user cannot create a new QR when at limit, even if all their codes are disabled

### Ownership check
- [ ] Accessing `/qr/{id}` for another user's QR code returns 404 (not the code)

### Downloads
- [ ] PNG download works (requires GD extension; `php -m | grep -i gd`)
- [ ] SVG download works (no GD required)
- [ ] Users without `can_export_png = true` receive an entitlement error for PNG
- [ ] Users without `can_export_svg = true` receive an entitlement error for SVG
- [ ] PNG and SVG downloads apply the saved custom style (custom foreground/background colors match the in-app preview)
- [ ] PNG and SVG downloads for QR codes with no custom style use default black-on-white

### QR color customization

**Entitlement gating:**
- [ ] Free plan user: "Customize QR" button on detail page links to `/qr/{id}/style`; style page shows upgrade warning card
- [ ] Free plan user: submitting the color form at `/qr/{id}/style` returns 403 (entitlement block)
- [ ] Starter+ user: style page loads with color picker inputs enabled

**Save colors:**
- [ ] Saving valid high-contrast colors (e.g. `#0000FF` foreground on `#FFFFFF` background) succeeds; flash "QR style saved"
- [ ] Detail page preview updates to reflect the new custom colors after save
- [ ] PNG and SVG downloads reflect the new custom colors after save
- [ ] Saving identical foreground and background colors shows validation error: "colors must be different"
- [ ] Saving low-contrast color pair shows validation error about contrast
- [ ] Saving an invalid hex value (e.g. `#ZZZZZZ`) shows validation error
- [ ] After a validation error, page re-renders with submitted values pre-filled in the color inputs

**Reset to default:**
- [ ] "Reset to Default" button appears only when a custom style is saved (`is_custom = true`)
- [ ] Clicking Reset to Default removes the custom style; preview reverts to black-on-white
- [ ] "Reset to Default" button is absent on a QR with no custom style

**Transparent background:**
- [ ] Free plan user: transparent checkbox is disabled (fieldset disabled); POST with background_transparent returns 403
- [ ] Starter+ user: transparent checkbox is visible and submittable
- [ ] Enabling transparent background and saving → flash "QR style saved"; preview shows checkerboard pattern
- [ ] PNG download has a transparent background (open in image editor to confirm alpha channel)
- [ ] SVG download has a transparent/no-fill background
- [ ] Foreground color still applies on transparent QR
- [ ] Logo renders correctly on a transparent-background QR
- [ ] Contrast validation still runs against the stored background color even when transparent is checked
- [ ] Reset to Default clears transparency; preview reverts to opaque black-on-white
- [ ] `background_transparent` in `qr_code_styles` table matches enabled/disabled state after save

**Audit log:**
- [ ] `style_updated` audit entry includes `old_background_transparent` and `new_background_transparent`
- [ ] `style_reset` audit entry includes `old_background_transparent` and `new_background_transparent: false`

### QR module style

**Entitlement gating:**
- [ ] Free plan user can view the style page; Module Style select is disabled and shows "Module styles are available on Starter, Pro, and Team plans."
- [ ] Free plan user cannot save a gapped/circle module style — hidden POST tampering with `module_style=circle` is rejected with 403
- [ ] Starter user sees the Module Style select enabled and can save `gapped_square`
- [ ] Pro user can save `circle`; Team user can save `circle`
- [ ] Saving an invalid module_style value (e.g. `triangle`) is rejected with a validation error

**Rendering:**
- [ ] Saving `gapped_square` updates the in-app SVG preview to show modules as smaller centered squares
- [ ] Saving `circle` updates the preview to show modules as centered dots
- [ ] PNG download reflects the saved module style (gapped or circle)
- [ ] SVG download reflects the saved module style (uses `<rect>` for gapped, `<circle>` for circle)
- [ ] The three finder-pattern eyes (top-left, top-right, bottom-left 7x7 blocks) remain classic squares for every module style — visually verifiable
- [ ] QR codes with `module_style = 'square'` produce byte-identical output to the prior renderer (default flow uses endroid writers)
- [ ] Custom colors + non-square module style render correctly together
- [ ] Transparent background + non-square module style renders correctly (modules drawn over transparent background)
- [ ] Logo overlay still renders on top of non-square modules; logo is centered

**Error correction:**
- [ ] After saving a non-square module style with no logo: `error_correction_level` in `qr_code_styles` is `Q`
- [ ] After saving any non-square module style with a logo present: ECL stays `H` (logo wins)
- [ ] Saving `module_style = 'square'` with default colors does NOT bump ECL to Q

**Reset:**
- [ ] Reset to Default removes the style row → next render shows classic squares (default style)
- [ ] After reset, the Module Style select returns to "Classic squares" on next page load

**Audit log:**
- [ ] `style_updated` audit entry includes `old_module_style` and `new_module_style`
- [ ] `style_reset` audit entry includes `old_module_style` and `new_module_style: square`

**Pricing/subscription display:**
- [ ] `/pricing` — "Custom QR module styles" row shows ✓ for Starter, Pro, Team and — for Free
- [ ] `/account/subscription` — same

### QR logo upload

**Entitlement gating:**
- [ ] Free plan user: style page shows "Logo in QR code — Available on Pro and Team plans"; no upload form shown
- [ ] Starter user: color picker enabled but no logo upload form; POST to `/qr/{id}/style/logo` returns 403
- [ ] Pro user: upload form visible with "max 250 KB / 20% of QR width" note

**Upload — valid files:**
- [ ] Pro user uploads valid PNG under 250 KB → succeeds; flash "Logo uploaded successfully"
- [ ] Pro user uploads valid JPG/JPEG → succeeds
- [ ] Pro user uploads valid WEBP → succeeds
- [ ] Team user uploads file up to 500 KB → succeeds
- [ ] QR preview on style page shows logo after upload
- [ ] PNG download includes the logo
- [ ] SVG download includes the logo (embedded as `<image>` element)

**Upload — rejected files:**
- [ ] Pro user uploads file over 250 KB → rejected: "Logo image is too large for your current plan."
- [ ] SVG file upload rejected: "Logo must be a PNG, JPG, or WEBP image."
- [ ] Text file renamed `.png` rejected: "The uploaded file does not appear to be a valid image."
- [ ] Submit with no file selected → rejected: "Please choose a logo image to upload."

**Replacement:**
- [ ] Uploading a new logo when one already exists → old file replaced; new logo visible in preview
- [ ] Old logo file is removed from `storage/qr-logos/` when replaced

**Remove logo:**
- [ ] "Remove Logo" button visible only when a logo is currently enabled
- [ ] Removing logo: QR reverts to color-only or default; "Logo removed" flash shown
- [ ] After removal with custom colors still set: ECL reverts to Q (visible in audit log)
- [ ] After removal with default colors: ECL reverts to M

**Reset All:**
- [ ] "Reset All to Default" button shown when logo is active (replaces "Reset to Default")
- [ ] Clicking it removes logo file and resets colors; QR reverts to black-on-white default

**Error correction:**
- [ ] Logo-enabled QR uses ECL=H — visible in `error_correction_level` column in `qr_code_styles`

**Audit log:**
- [ ] `qr_logo_uploaded` audit entry: original_filename, mime_type, size_bytes, max_size_kb, logo_percent, old_logo_present — no filesystem path in metadata
- [ ] `qr_logo_removed` audit entry: old_logo_original_filename, old_logo_mime_type, old_logo_size_bytes

**Pricing/subscription display:**
- [ ] `/pricing` — QR Logo Upload row shows ✓ for Pro and Team, — for Free and Starter; no "coming soon" label
- [ ] `/account/subscription` — same

---

## 4. Redirect / Public Behavior

- [ ] Visiting `/{slug}` for an active link redirects (HTTP 302) to the destination
- [ ] Visiting `/{slug}` for a paused link shows the unavailable page
- [ ] Visiting `/{slug}` for an archived link shows the unavailable page
- [ ] Visiting `/{slug}` for an admin-disabled link shows the unavailable page (generic message — no reason shown)
- [ ] Visiting `/{slug}` for a nonexistent slug returns 404
- [ ] A scan event row is inserted in `scan_events` after a successful redirect

---

## 5. Analytics

- [ ] `/qr/{id}/analytics` loads with scan summary cards
- [ ] Date range filter (from/to) narrows results
- [ ] Bot toggle shows/hides bot traffic
- [ ] Analytics are clamped to the plan's `analytics_retention_days`
- [ ] User without `can_export_analytics = true` cannot access CSV export; entitlement error shown
- [ ] User with `can_export_analytics = true`: `/qr/{id}/analytics/export` downloads a CSV with headers

---

## 6. Subscription Lifecycle

### Public pricing page
- [ ] `/pricing` loads without login; shows public active non-legacy plans
- [ ] Authenticated user sees which plan they are currently on

### Account subscription page
- [ ] `/account/subscription` shows current plan, usage summary, and plan comparison table
- [ ] Pricing shown with "informational only / no checkout" notice

### Switch to Free
- [ ] User on a paid plan can switch to Free immediately; new subscription created; old one canceled

### Request paid plan
- [ ] User on Free submits a request for a paid plan → `subscription_change_requests` row created with `pending` status
- [ ] Request appears in "Pending Plan-Change Requests" section of subscription page
- [ ] User cannot submit a duplicate pending request for the same plan

### Cancel user's own request
- [ ] Cancel button on pending request removes it from Pending section; status changes to `canceled`
- [ ] User's subscription is unchanged after canceling their own request

---

## 7. Admin Tooling

### Access control
- [ ] Non-admin accessing `/admin` returns 403
- [ ] Non-admin POSTing to `/admin/users/{id}/subscription` returns 403

### User list / detail
- [ ] `/admin/users` lists all users with email search working
- [ ] `/admin/users/{id}` shows subscription history, entitlements, and overrides
- [ ] Billing state columns (`billing_status`, etc.) visible in subscription history table (all `—` for manual plans)

### Change subscription (admin)
- [ ] Assigning a plan cancels the current active subscription and creates a new one
- [ ] Audit log entry created for the subscription change

### Feature overrides
- [ ] Adding an override for `max_qr_codes` replaces plan value in entitlements
- [ ] Deleting the override restores plan value
- [ ] Audit log entries created for add and delete

### Subscription request review
- [ ] `/admin/subscription-requests` lists pending requests
- [ ] Approve: closes current subscription, creates new one; request marked `approved`
- [ ] Deny: request marked `denied`; user's subscription unchanged
- [ ] Admin cancel: request marked `canceled`

### Plan catalog
- [ ] `/admin/plans` lists all plans with flags
- [ ] Create plan: valid submission creates plan and redirects to detail
- [ ] Create plan: duplicate `internal_name` rejected
- [ ] Edit plan: metadata changes save; `internal_name` field is read-only
- [ ] Add / update / delete plan features
- [ ] Clone plan: creates new plan with all features; source unchanged
- [ ] Retire plan: sets `is_public=0`, `is_legacy=1`, `is_active=1`
- [ ] Billing price mapping: add a mapping with provider and price ID; appears in table
- [ ] Billing price mapping: deactivate/activate toggle works
- [ ] Health warning shown when a paid plan has no active billing price mapping

### Audit log browser
- [ ] `/admin/audit-logs` lists entries with action/entity filters working
- [ ] `/admin/audit-logs/{id}` shows full metadata JSON

### Subscription history
- [ ] `/admin/subscriptions` lists subscriptions with user email, plan, status, billing_status filters

### Operations page
- [ ] `/admin/ops` shows PHP version, extension status, migration count, DB counters
- [ ] Mail Configuration section shows "configured" (not "OK" / "ready") for MAIL_ENABLED when enabled
- [ ] Mail Configuration section does not show `MAIL_SMTP_PASSWORD`
- [ ] Explanatory note reads: "Configured means the app has enough settings to attempt delivery."
- [ ] Send Test Email form pre-fills recipient with the current admin's email address
- [ ] Submitting invalid email address shows validation error flash, no email attempted
- [ ] `MAIL_ENABLED=false` → failure flash "Test email could not be sent…"; no crash
- [ ] Missing CSRF token on POST → rejected
- [ ] Non-admin user cannot POST to `/admin/ops/send-test-email` (returns 403)
- [ ] Valid SMTP: test email delivered; success flash "Test email sent to {address}."
- [ ] Bad SMTP host: failure flash shown; error logged to `storage/logs/error.log`; no stack trace in browser
- [ ] Send Test Email must be tested before enabling Stripe/payment flows to confirm delivery

---

## 8. Moderation

### Blocked domains
- [ ] `/admin/moderation/domains` loads and shows existing blocked domains
- [ ] Adding `example.com` blocks future QR creation pointing to `https://example.com` or `https://sub.example.com`
- [ ] Adding a malformed domain (spaces, consecutive dots, etc.) is rejected
- [ ] Adding a duplicate domain shows error — not a 500
- [ ] Toggle deactivates/activates; inactive entries no longer block destinations

### Link moderation
- [ ] `/admin/moderation/links` loads with status filter (default: disabled)
- [ ] Disable a link: requires reason; sets `status = 'disabled'`; public redirect shows unavailable page
- [ ] User's own detail page shows "disabled by an administrator" banner
- [ ] Restore a disabled link: sets `status = 'active'`; short URL redirects again
- [ ] Audit log entries written for disable and restore actions

---

## 9. Policy Pages

- [ ] `/terms` loads without login
- [ ] `/privacy` loads without login
- [ ] `/acceptable-use` loads without login
- [ ] `/abuse` loads without login; shows `ABUSE_EMAIL` value
- [ ] `/contact` loads without login; shows `SUPPORT_EMAIL`, `ABUSE_EMAIL`, `PRIVACY_EMAIL` values
- [ ] All five pages linked in site footer
- [ ] Slugs `terms`, `privacy`, `acceptable-use`, `abuse`, `contact` rejected as user short-link slugs

---

## 10. Security Spot Checks

- [ ] Non-admin user: GET `/admin` → 403 page
- [ ] Non-admin user: POST `/admin/users/{id}/subscription` → 403 page
- [ ] POST `/logout` with no CSRF token → 403 page
- [ ] POST `/qr` (create) with no CSRF token → 403 page
- [ ] POST `/account/settings/password` with no CSRF token → 403 page
- [ ] `APP_DEBUG=false`: triggering a 500 (e.g. bad DB credentials) shows generic error page — no stack trace, no file paths in response
- [ ] `storage/logs/error.log` receives the stack trace when a 500 occurs (verify file exists and has content)
- [ ] Analytics CSV export: spot-check that values starting with `=`, `+`, `-`, `@` are not present as raw formula starters in URL/referer cells (these are written as-is; if formula injection is a concern, verify the export escapes or wraps them)

---

## 11. Route / Nav Sanity

- [ ] All nav links visible to a logged-in non-admin user point to working pages: `/`, `/pricing`, `/dashboard`, `/qr`, `/account/settings`, `/account/subscription`
- [ ] Admin nav link (`/admin`) only visible when `role = 'admin'`
- [ ] Footer links all resolve: `/terms`, `/privacy`, `/acceptable-use`, `/abuse`, `/contact`
- [ ] `/{slug}` catch-all is the last registered route (confirmed in `public/index.php`)
- [ ] Visiting `/account` (no trailing path) redirects to `/account/settings`

---

## 12. Stripe Configuration (Phase 35 — Ops Readiness)

> These checks apply even before Stripe is enabled. They verify that the SDK is installed,
> env vars are wired, and the ops page reflects the correct status.

### SDK and config
- [ ] `composer install` completes without errors after `stripe/stripe-php ^16.0` is added to `composer.json`
- [ ] `vendor/stripe/stripe-php/` directory exists after install
- [ ] `/admin/ops` Stripe Configuration section loads without error
- [ ] With `STRIPE_ENABLED=false` in `.env`: ops page shows "disabled" for STRIPE_ENABLED
- [ ] With `STRIPE_ENABLED=true` and all required vars set: ops page shows "enabled", correct mode, "configured" for keys
- [ ] `STRIPE_SECRET_KEY` value is **never** displayed on the ops page — only "configured" or "not set"
- [ ] `STRIPE_WEBHOOK_SECRET` value is **never** displayed on the ops page — only "configured" or "not set"
- [ ] `STRIPE_PUBLISHABLE_KEY` value is **not** shown — only "configured" or "not set" (consistent with all other keys)
- [ ] `STRIPE_MODE=live` shows a yellow warning ("live — real charges will be made")
- [ ] SDK row shows "present" when `stripe/stripe-php` is installed; "not found — run: composer require stripe/stripe-php" when missing

### Validation
- [ ] With `STRIPE_ENABLED=true` and `STRIPE_SECRET_KEY=` blank in `.env`: app returns 500 on boot (or CLI exits 1) with config error naming the missing var
- [ ] With `STRIPE_ENABLED=true` and `STRIPE_MODE=badvalue`: app returns 500 on boot with "STRIPE_MODE must be 'test' or 'live'"
- [ ] With `STRIPE_ENABLED=false`: leaving `STRIPE_SECRET_KEY` blank does **not** cause a startup error

### Price coverage
- [ ] Ops page shows "Active Stripe prices: 0" when no `plan_billing_prices` rows exist for provider=stripe
- [ ] After adding active Stripe price mappings via admin: count increments correctly
- [ ] "Paid plans missing active Stripe price" shows plan names correctly when paid plans lack prices
- [ ] "Paid plans missing active Stripe price" shows "none" with OK indicator when all paid plans have coverage

### Migration
- [ ] `php migrate.php` after adding migration 027 reports `RUN 027_add_stripe_customer_id_to_users ... done`
- [ ] `DESCRIBE users;` in MySQL shows `stripe_customer_id VARCHAR(255) NULL` column present
- [ ] Re-running `php migrate.php` shows `SKIP 027_add_stripe_customer_id_to_users` (idempotent)

### Webhook table placeholder
- [ ] Before Phase 37 migration: ops page shows "Webhook events table: not yet created (Phase 37)" — no error
- [ ] After Phase 37 migration: ops page shows latest webhook timestamp and 24 h failure count

---

## 13. Stripe Checkout Session (Phase 36)

> These checks require `STRIPE_ENABLED=true`, valid credentials, and at least one active
> Stripe price mapping in `plan_billing_prices`. Use Stripe test mode throughout.

### UI — Stripe disabled
- [ ] With `STRIPE_ENABLED=false`: subscription page shows "Request Review" for paid plans
- [ ] With `STRIPE_ENABLED=false`: pricing page shows "Request Review" for paid plans
- [ ] With `STRIPE_ENABLED=false`: submitting a paid plan via `/account/subscription/change` creates a pending request as before

### UI — Stripe enabled, no price mapped
- [ ] With `STRIPE_ENABLED=true` and no active Stripe price for a paid plan: subscription page shows "Online checkout not configured" (disabled button)
- [ ] With `STRIPE_ENABLED=true` and no active Stripe price: pricing page shows "Not available" (disabled button)

### UI — Stripe enabled, price mapped
- [ ] With `STRIPE_ENABLED=true` and active monthly Stripe price: subscription page shows "Subscribe" button posting to `/account/subscription/checkout` with `billing_cycle=monthly`
- [ ] With `STRIPE_ENABLED=true` and only yearly price: button uses `billing_cycle=yearly`
- [ ] Clicking Subscribe on subscription page (with active price): user is redirected to Stripe-hosted Checkout URL (not a local page)
- [ ] Clicking Subscribe on pricing page: same redirect behavior

### Security guards
- [ ] Submitting `/account/subscription/checkout` without a CSRF token → 403
- [ ] Submitting `/account/subscription/checkout` when logged out → redirect to `/login`
- [ ] Submitting `/account/subscription/checkout` with unverified email → redirect to `/account/verify-email` (no checkout created)
- [ ] Submitting `/account/subscription/checkout` with `plan_id` of a free plan → error flash, no checkout created
- [ ] Submitting `/account/subscription/checkout` with `plan_id` of a non-public plan → error flash
- [ ] Submitting `/account/subscription/checkout` with `billing_cycle=weekly` (invalid) → error flash
- [ ] Submitting `/account/subscription/change` for a paid plan when `STRIPE_ENABLED=true` → error flash: "Online checkout is now used for paid plans. Please use the Subscribe button."

### Checkout session tracking
- [ ] After a successful checkout redirect: `SELECT * FROM stripe_checkout_sessions` shows one row with `status='pending'`
- [ ] Row has correct `user_id`, `plan_id`, `plan_billing_price_id`, `stripe_session_id`
- [ ] `checkout_url` is populated in the local row
- [ ] `stripe_customer_id` is populated in the local row

### Customer persistence
- [ ] After first checkout attempt: `SELECT stripe_customer_id FROM users WHERE id = ?` returns a non-null `cus_*` value
- [ ] After second checkout attempt for the same user: no new Stripe customer is created; existing `stripe_customer_id` is reused (verify in Stripe Dashboard test mode)

### Return URLs
- [ ] Completing payment in Stripe test mode: browser returns to `STRIPE_SUCCESS_URL` (e.g. `/account/subscription?checkout=success`)
- [ ] Success return shows info banner: "Checkout completed. Your subscription will update after Stripe confirms payment."
- [ ] No paid subscription row is created in `user_subscriptions` from the browser return alone
- [ ] Canceling checkout in Stripe: browser returns to `STRIPE_CANCEL_URL` (e.g. `/account/subscription?checkout=canceled`)
- [ ] Cancel return shows info banner: "Checkout was canceled. Your subscription was not changed."
- [ ] User's plan and `user_subscriptions` row are unchanged after cancel

---

## 14. Stripe — Webhook Processing (Phase 37)

**Prerequisites:** `STRIPE_ENABLED=true`, Stripe SDK installed, migration 029 run, `STRIPE_WEBHOOK_SECRET` set.
Test with [Stripe CLI](https://stripe.com/docs/stripe-cli): `stripe listen --forward-to http://localhost:8000/stripe/webhook`

### Webhook signature verification
- [ ] POST `/stripe/webhook` without a `Stripe-Signature` header → HTTP 400, body `Missing Stripe-Signature header.`
- [ ] POST `/stripe/webhook` with an invalid signature → HTTP 400, body `Signature verification failed.`
- [ ] POST `/stripe/webhook` with a valid signature and known event type → HTTP 200, body `OK`

### Idempotency
- [ ] Replay the same event (same `stripe_event_id`) a second time → HTTP 200 (no error); `stripe_webhook_events` still has only one row for that event ID
- [ ] `processing_status` on the first row is unchanged after the replay

### Event recording
- [ ] After a successful event: `SELECT * FROM stripe_webhook_events` shows one row per event with `processing_status='processed'` and non-null `processed_at`
- [ ] Unknown/unhandled event type (e.g. `payment_intent.created`): row has `processing_status='ignored'`

### `checkout.session.completed` — subscription activation
- [ ] Trigger `checkout.session.completed` via Stripe CLI: `stripe trigger checkout.session.completed`
  - Or complete a real test-mode checkout session
- [ ] After event: `stripe_checkout_sessions` row for the session has `status='completed'`, `completed_at` set
- [ ] After event: `user_subscriptions` has a new row with `status='active'`, `billing_provider='stripe'`, `provider_subscription_id` set
- [ ] New row has `billing_status='active'` (or `'trialing'` if Stripe subscription is trialing)
- [ ] New row has `current_period_start` and `current_period_end` populated from Stripe
- [ ] Previous active subscription for the user has `status='canceled'`, `canceled_at` set
- [ ] `audit_logs` has an entry with `action='stripe_checkout_completed'` and correct metadata
- [ ] Entitlement cache cleared (user immediately sees new plan features on refresh)

### `checkout.session.expired` — expired checkout
- [ ] Trigger `checkout.session.expired`: after event, local `stripe_checkout_sessions` row has `status='expired'`
- [ ] No changes to `user_subscriptions`

### Mode = non-subscription ignored gracefully
- [ ] A `checkout.session.completed` event with `mode != 'subscription'` does not crash; `processing_status='ignored'`

### Admin ops — webhook stats
- [ ] `/admin/ops` Stripe section shows "Webhook events (total)" count
- [ ] After processing an event: "Latest processed" shows a recent timestamp
- [ ] After a failed event (simulate by breaking DB temporarily): "Failed webhooks (24 h)" count is > 0 and shows warning
- [ ] Table exists warning gone (was "not yet created (Phase 37)")

---

## 15. Stripe — Subscription Lifecycle (Phase 38)

**Prerequisites:** Phase 37 prerequisites met. Use Stripe CLI to trigger events or complete real test-mode checkouts.

### Billing-status gating (EntitlementService)
- [ ] User with `billing_status='active'` subscription: paid plan features available normally
- [ ] User with `billing_status='past_due'`: paid plan features still available (grace window)
- [ ] User with `billing_status='unpaid'` or `'incomplete'`: entitlements fall back to Free plan immediately
- [ ] User with `billing_status='canceled'` and `current_period_end` in the future: paid features still available
- [ ] User with `billing_status='canceled'` and `current_period_end` in the past (or null): entitlements fall back to Free plan
- [ ] No active subscription: entitlements fall back to Free plan

### Billing banners
- [ ] `billing_status='past_due'`: subscription page and dashboard show an error banner about payment failure
- [ ] `billing_status='unpaid'` or `'incomplete'`: subscription page and dashboard show error banner about limited access
- [ ] `billing_status='canceled'` with future period end: subscription page and dashboard show info banner with access-until date
- [ ] `billing_status='canceled'` with past period end: subscription page and dashboard show error banner (plan ended)
- [ ] `cancel_at_period_end=1` (active, not yet canceled): subscription page and dashboard show info banner with cancels-on date
- [ ] No billing issue: no banner shown

### Subscription page — billing table rows
- [ ] `billing_status` row visible in Current Plan table when billing_status is not `not_applicable`
- [ ] `billing_status='past_due'` shown as "past due" (underscores replaced with spaces)
- [ ] "Renews On" row shows `current_period_end` formatted as `Month D, YYYY` when subscription is active
- [ ] "Access Until" label shown instead of "Renews On" when `cancel_at_period_end=1` or `billing_status='canceled'`

### Cancel subscription (cancel-at-period-end)
- [ ] Cancel button visible on subscription page when: Stripe-backed, `status='active'`, `cancel_at_period_end=0`, `billing_status` not canceled/unpaid/incomplete
- [ ] Cancel button not visible when `cancel_at_period_end=1` (already scheduled)
- [ ] Cancel button not visible when `billing_status='canceled'`
- [ ] Cancel button not visible when `billing_provider` is not `'stripe'`
- [ ] Clicking Cancel and confirming the confirmation dialog: `POST /account/subscription/cancel-stripe` is submitted
- [ ] Without CSRF token → 403
- [ ] While logged out → redirect to `/login`
- [ ] With unverified email → redirect to `/account/verify-email`
- [ ] Success: flash message confirming cancellation scheduled; `cancel_at_period_end=1` in `user_subscriptions`; Stripe subscription now has `cancel_at_period_end: true`
- [ ] After cancel: Cancel button no longer shown; info banner appears with cancels-on date

### Webhook lifecycle handlers

#### `customer.subscription.updated`
- [ ] Trigger via Stripe CLI: `stripe trigger customer.subscription.updated`
- [ ] Local `user_subscriptions` row updated: `billing_status`, `current_period_end`, `cancel_at_period_end` synced
- [ ] If Stripe subscription is `canceled`: local row also gets `status='canceled'`, `canceled_at` set
- [ ] Entitlement cache cleared; refreshing shows updated plan access
- [ ] `audit_logs` entry with `action='stripe_subscription_updated'`
- [ ] Webhook event recorded with `processing_status='processed'`

#### `customer.subscription.deleted`
- [ ] Trigger via Stripe CLI: `stripe trigger customer.subscription.deleted`
- [ ] Local `user_subscriptions` row: `billing_status='canceled'`, `status='canceled'`, `canceled_at` set
- [ ] Entitlement cache cleared; user immediately falls back to Free plan features
- [ ] Notification email sent to user (subscription ended)
- [ ] `audit_logs` entry with `action='stripe_subscription_deleted'`

#### `invoice.payment_succeeded`
- [ ] Trigger via Stripe CLI: `stripe trigger invoice.payment_succeeded`
- [ ] Local subscription `billing_status='active'`; `current_period_start/end` updated from invoice period
- [ ] Entitlement cache cleared
- [ ] `audit_logs` entry with `action='stripe_invoice_paid'`

#### `invoice.payment_failed`
- [ ] Trigger via Stripe CLI: `stripe trigger invoice.payment_failed`
- [ ] Local subscription `billing_status='past_due'`
- [ ] Entitlement cache cleared; paid features remain accessible
- [ ] Notification email sent to user (payment failed)
- [ ] `audit_logs` entry with `action='stripe_invoice_payment_failed'`

### Notification emails
- [ ] Payment-failed notification email received when `invoice.payment_failed` fires (with `MAIL_ENABLED=true`)
- [ ] Cancellation-scheduled notification email received after `POST /account/subscription/cancel-stripe`
- [ ] Subscription-ended notification email received when `customer.subscription.deleted` fires

### Admin Ops — subscription billing state
- [ ] `/admin/ops` shows "Subscription Billing State" section when DB is connected
- [ ] Counts reflect current `user_subscriptions` billing_status distribution
- [ ] "Past due" and "Unpaid" rows show a warning indicator when count > 0
- [ ] "Canceling at period end" count shown without warning (informational)

---

## 16. Known Gaps (Not Blockers for Current Launch)

These are intentional absences. Confirm they are clearly communicated to users where applicable:

| Gap | Status |
|-----|--------|
| Online checkout / Stripe payment processing | Fully implemented through Phase 38: checkout session creation, webhook activation, lifecycle sync, billing-status gating, cancel-at-period-end flow. |
| Password reset by email | Implemented (Phase 24) |
| Email notifications of any kind | Implemented (Phase 27) |
| Multi-factor authentication (MFA) | Not implemented |
| Team / workspace / multi-user features | Not implemented |
| API endpoints | Not implemented |
| External malware / phishing URL scanning | Not implemented |
| Geolocation in scan analytics | Not implemented (fields stored as NULL) |
| Analytics data purge | Not implemented (retention is query filter only) |
| Hard deletion of QR codes | Not implemented — archive is the only retire action; slugs remain reserved |

---

## 17. Stripe Test-Mode End-to-End QA

Complete this section using `STRIPE_MODE=test` before switching to live. All webhook tests require the Stripe CLI listener running.

### Prerequisites
- [ ] `.env`: `STRIPE_ENABLED=true`, `STRIPE_MODE=test`, test-mode `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET` all set
- [ ] Stripe CLI installed and authenticated (`stripe login`)
- [ ] Webhook listener started: `stripe listen --forward-to http://localhost:8000/stripe/webhook` — copy the printed webhook secret into `STRIPE_WEBHOOK_SECRET`
- [ ] At least one paid plan has an active Stripe price mapped (`/admin/plans/{id}`)
- [ ] A test user exists with a verified email address

### Configuration
- [ ] `/admin/ops` shows `STRIPE_ENABLED: enabled`, `STRIPE_MODE: test`
- [ ] All three key rows show "configured" (no values displayed)
- [ ] Stripe SDK shows "present"
- [ ] Active Stripe prices count > 0; no plans missing active price

### Checkout
- [ ] Unauthenticated user visits `/pricing` → Subscribe buttons visible for paid plans
- [ ] Authenticated user with verified email clicks Subscribe → redirected to Stripe Checkout (URL contains `checkout.stripe.com`)
- [ ] Stripe Checkout page shows the correct plan name and price
- [ ] Enter test card `4242 4242 4242 4242`, any future expiry, any CVC → payment succeeds
- [ ] On success: redirected to `/account/subscription?checkout=success` with success flash message
- [ ] `stripe_checkout_sessions` row: `status='completed'`, `completed_at` set
- [ ] `user_subscriptions` row: `status='active'`, `billing_status='active'`, `billing_provider='stripe'`, Stripe IDs populated, period dates set
- [ ] Audit log entry: `action='stripe_checkout_completed'`
- [ ] Subscription page shows correct plan name, billing status, and next renewal date

### Webhook delivery
- [ ] Stripe CLI terminal shows `200` for each forwarded event
- [ ] No failed webhooks in `/admin/ops` "Failed webhooks (24 h)"
- [ ] Replay: `stripe events resend <evt_id>` → second delivery returns `200`; no duplicate `user_subscriptions` row (idempotent)

### `invoice.payment_succeeded`
- [ ] Trigger: `stripe trigger invoice.payment_succeeded`
- [ ] `billing_status` remains `active`; `current_period_end` updated if changed
- [ ] Audit log entry: `action='stripe_invoice_paid'`

### `invoice.payment_failed`
- [ ] Trigger: `stripe trigger invoice.payment_failed`
- [ ] `billing_status` set to `past_due`
- [ ] Subscription page and dashboard show payment-failed banner (warning style)
- [ ] User retains paid-plan features (grace period active)
- [ ] Notification email received (requires `MAIL_ENABLED=true`)
- [ ] Audit log entry: `action='stripe_invoice_payment_failed'`
- [ ] `/admin/ops` "Past due" count > 0 with warning indicator

### Cancellation
- [ ] Authenticated subscriber visits `/account/subscription` → "Cancel Subscription" button visible
- [ ] User submits cancellation → Stripe subscription set to `cancel_at_period_end=true`
- [ ] Page shows info banner: subscription cancels at end of billing period
- [ ] "Cancel Subscription" button no longer shown; "Switch to Free" column shows disabled label
- [ ] Notification email received: cancellation scheduled (requires `MAIL_ENABLED=true`)
- [ ] Audit log entry: `action='stripe_subscription_cancel_requested'`
- [ ] `/admin/ops` "Canceling at period end" count = 1
- [ ] Trigger deletion: `stripe trigger customer.subscription.deleted`
- [ ] `billing_status='canceled'`, `status='canceled'`
- [ ] If `current_period_end` > now: subscription page shows "Access Until" date and info banner; paid features accessible
- [ ] Notification email received: subscription ended (requires `MAIL_ENABLED=true`)

### Entitlement downgrade after cancellation
- [ ] Immediately after `customer.subscription.deleted` with future `current_period_end`: user has paid access
- [ ] Update DB row: set `current_period_end` to a past timestamp → user falls back to Free plan, paid features inaccessible
- [ ] Restore `current_period_end` to future → paid access returns

### Notifications
- [ ] `invoice.payment_failed` → payment-failed email received
- [ ] Cancel form submission → cancellation-scheduled email received
- [ ] `customer.subscription.deleted` → subscription-ended email received
- [ ] All email links use the correct `APP_URL`

### Admin Ops
- [ ] After test checkout: "Active" billing-state count = 1
- [ ] After `invoice.payment_failed`: "Past due" count = 1, warning shown
- [ ] After cancel scheduled: "Canceling at period end" count = 1
- [ ] After `customer.subscription.deleted`: counts reflect updated state

---

## 18. Stripe — Provider Mode and Billing Cycle Selection (Phase 40)

**Prerequisites:** Migration 030 run. At least one paid plan exists. `STRIPE_ENABLED=true`.

### Migration
- [ ] Run migration 030 — `plan_billing_prices.provider_mode` column added (ENUM `test`/`live`, default `test`)
- [ ] Existing rows have `provider_mode='test'` by default

### Admin — adding price mappings
- [ ] Admin visits `/admin/plans/{id}` — "Add Price Mapping" form shows "Provider Mode" select (options: test, live)
- [ ] Default selection matches current `STRIPE_MODE` (test or live)
- [ ] Submit with `provider_mode='test'` and a test Price ID (`price_test_...`) — row created successfully
- [ ] Submit with `provider_mode='live'` and a live Price ID — row created successfully
- [ ] Submit with a Product ID (`prod_...`) — validation error returned, no row created
- [ ] Submit with an empty provider_mode — validation error returned

### Admin — billing prices table
- [ ] Billing prices table on plan detail shows "Mode" column
- [ ] Test-mode mappings show "test" (muted style)
- [ ] Live-mode mappings show "live" (success/green style)
- [ ] Activate/deactivate toggle still works

### Checkout mode filtering
- [ ] `STRIPE_MODE=test`: Subscribe buttons only appear for plans with active **test**-mode price mappings
- [ ] `STRIPE_MODE=test`: Plans with only active **live**-mode mappings show "Online checkout not configured"
- [ ] `STRIPE_MODE=live`: Subscribe buttons only appear for plans with active **live**-mode price mappings
- [ ] `STRIPE_MODE=live`: Plans with only active **test**-mode mappings show unavailable state
- [ ] Inactive mappings (any mode) do not enable checkout

### Monthly / yearly buttons
- [ ] Plan with only monthly test mapping: shows "Subscribe Monthly" button only
- [ ] Plan with only yearly test mapping: shows "Subscribe Yearly" button only
- [ ] Plan with both monthly and yearly test mappings: shows both "Subscribe Monthly" and "Subscribe Yearly" buttons
- [ ] Monthly button POSTs `billing_cycle=monthly`
- [ ] Yearly button POSTs `billing_cycle=yearly`
- [ ] Server correctly creates Stripe Checkout Session for monthly cycle
- [ ] Server correctly creates Stripe Checkout Session for yearly cycle

### Ops page
- [ ] `/admin/ops` shows "Active prices (test mode)" and "Active prices (live mode)" as separate rows
- [ ] "Paid plans missing active price" row title includes the current mode name
- [ ] Counts update correctly after adding/toggling mappings

---

*Last updated: 2026-05-15 — Phase 40: provider mode and billing cycle selection QA section added*
