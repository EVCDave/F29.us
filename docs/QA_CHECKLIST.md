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

**Audit log:**
- [ ] `style_updated` audit entry created when colors are saved (includes foreground and background)
- [ ] `style_reset` audit entry created when reset to default

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

## 12. Known Gaps (Not Blockers for Current Launch)

These are intentional absences. Confirm they are clearly communicated to users where applicable:

| Gap | Status |
|-----|--------|
| Online checkout / Stripe payment processing | Not implemented (schema groundwork only) |
| Password reset by email | Not implemented |
| Email notifications of any kind | Not implemented |
| Multi-factor authentication (MFA) | Not implemented |
| Team / workspace / multi-user features | Not implemented |
| API endpoints | Not implemented |
| External malware / phishing URL scanning | Not implemented |
| Geolocation in scan analytics | Not implemented (fields stored as NULL) |
| Analytics data purge | Not implemented (retention is query filter only) |
| Hard deletion of QR codes | Not implemented — archive is the only retire action; slugs remain reserved |

---

*Last updated: 2026-05-15*
