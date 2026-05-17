# f29.us Dynamic QR

Dynamic QR codes that you can redirect to any URL at any time — no reprinting required.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.2+ |
| MariaDB or MySQL | MariaDB 10.6+ / MySQL 8.0+ |
| Web server | Apache with `mod_rewrite`, or PHP built-in server for local dev |
| Composer | 2.x |

### PHP Extensions

| Extension | Purpose | Required |
|---|---|---|
| `pdo` | PDO base interface | Yes |
| `pdo_mysql` | MariaDB/MySQL driver | Yes |
| `gd` | PNG QR code generation | PNG downloads only |
| `mbstring` | Multibyte string validation | Yes |

Check which extensions are loaded: `php -m`

---

## Dependencies

| Package | Purpose |
|---|---|
| `endroid/qr-code ^5.0` | Server-side QR image generation (PNG + SVG) |
| `stripe/stripe-php ^16.0` | Stripe billing SDK — used by `StripeService` (inactive until `STRIPE_ENABLED=true`) |

PNG generation requires the **PHP GD extension** (`php-gd`). SVG generation is pure PHP — no extra extension needed.

Install dependencies:

```bash
composer install
```

---

## Local Setup

### 1. Copy and configure `.env`

```bash
cp .env.example .env
```

Edit `.env` with your values:

```
APP_NAME="f29.us Dynamic QR"
APP_ENV=local
APP_URL=http://localhost:8000
APP_DEBUG=true

# Required: HMAC secret for IP hashing in login throttle
# Generate: php -r "echo bin2hex(random_bytes(32));"
APP_KEY=your_generated_key_here

# Domain encoded into QR images — use the production URL even locally
# so downloaded QR codes point to the right place after deployment.
QR_BASE_URL=https://f29.us

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=f29us_qr
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

### 2. Install Composer dependencies

```bash
composer install
```

This installs `endroid/qr-code` and its dependencies into `vendor/`. The application will not boot without this step.

### 3. Create the database

```sql
CREATE DATABASE f29us_qr
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 4. Run migrations

From the project root:

```bash
php migrate.php
```

Expected output:

```
  RUN     001_create_users_table ... done
  RUN     002_create_plans_table ... done
  ...

Migrations: N run, 0 skipped.
```

Migrations are idempotent — re-running skips already-applied files.

### 5. Run seeders

```bash
php seed.php
```

Expected output:

```
  SEED    PlansSeeder ... done
  SEED    PlanFeaturesSeeder ... done

Seeding complete.
```

Seeders use `ON DUPLICATE KEY UPDATE` and are safe to re-run.

### 6. Serve locally

```bash
php -S localhost:8000 -t public
```

Open `http://localhost:8000` in your browser.

### 7. Register and promote the first admin

Register a user at `http://localhost:8000/register`, then promote them in the database:

```sql
UPDATE users SET role = 'admin' WHERE email = 'you@example.com';
```

Visit `http://localhost:8000/admin` — the yellow **Admin** link appears in the nav for admin users.

---

## Production Deployment

These steps cover a typical shared-hosting environment (cPanel). Adjust paths for your host.

### 1. Set the document root to `/public`

In cPanel → **Domains** (or **Addon Domains**), set the document root to the `public/` subdirectory of your project:

```
/home/youruser/f29us/public
```

Keep the project root one level above the web-accessible directory so `bootstrap.php`, `.env`, and `database/` are never served directly.

### 2. Upload files

Upload the project directory to `/home/youruser/f29us/` (or your preferred path). Exclude:

- `.env` — create this on the server (see step 3)
- `vendor/` — either install on the server or upload from local (see step 4)

### 3. Configure `.env`

On the server, copy `.env.example` to `.env` and set production values:

```
APP_ENV=production
APP_URL=https://f29.us
APP_DEBUG=false
APP_KEY=<generate below>
QR_BASE_URL=https://f29.us

DB_HOST=127.0.0.1
DB_DATABASE=cpanelusername_dbname
DB_USERNAME=cpanelusername_dbuser
DB_PASSWORD=your_strong_password
```

Generate `APP_KEY` on the server (or locally):

```bash
php -r "echo bin2hex(random_bytes(32));"
```

### 4. Install Composer dependencies

Via SSH or cPanel Terminal:

```bash
composer install --no-dev --optimize-autoloader
```

If your host has no SSH, run `composer install --no-dev` locally and upload the resulting `vendor/` directory.

### 5. Create the database

In cPanel → **MySQL Databases**:

1. Create a new database
2. Create a new database user with a strong password
3. Add the user to the database with **All Privileges**

Update `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`.

### 6. Run migrations and seeders

Via SSH or cPanel Terminal:

```bash
php migrate.php
php seed.php
```

If your host does not provide terminal access, look for a **Terminal** tool in cPanel under Advanced, or ask your host how to run PHP CLI scripts.

### 7. Verify file permissions

`bootstrap.php` creates `storage/logs/` automatically on first request. If the directory already exists but is not writable by the web server:

```bash
chmod 755 storage/logs
```

### 8. Configure the cleanup cron

In cPanel → **Cron Jobs**, add a daily job to prune old login attempt rows:

```
0 3 * * * php /home/youruser/f29us/cleanup.php >> /home/youruser/f29us/storage/logs/cleanup.log 2>&1
```

---

## Environment Variables

| Variable | Required | Default | Description |
|---|---|---|---|
| `APP_NAME` | No | `f29.us Dynamic QR` | Application name displayed in page titles |
| `APP_ENV` | No | `production` | Environment: `local`, `staging`, or `production` |
| `APP_URL` | **Yes** | — | Full base URL of your deployment (e.g. `https://f29.us`) |
| `APP_DEBUG` | No | `false` | Show PHP errors in browser. Set `true` for local dev only. |
| `APP_KEY` | **Yes** | — | HMAC secret for login throttle IP hashing. Minimum 32 characters. Generate: `php -r "echo bin2hex(random_bytes(32));"` |
| `QR_BASE_URL` | **Yes** | — | Domain encoded into QR images. Use the production URL always so downloaded QR codes work after deployment. |
| `DB_HOST` | **Yes** | — | Database host (usually `127.0.0.1` or `localhost`) |
| `DB_PORT` | No | `3306` | Database port |
| `DB_DATABASE` | **Yes** | — | Database name |
| `DB_USERNAME` | **Yes** | — | Database username |
| `DB_PASSWORD` | No | *(empty)* | Database password |
| `SUPPORT_EMAIL` | No | `support@f29.us` | Contact address shown on the Contact page |
| `ABUSE_EMAIL` | No | `abuse@f29.us` | Contact address shown on the Abuse and Contact pages |
| `PRIVACY_EMAIL` | No | `privacy@f29.us` | Contact address shown on the Contact page |
| `MAIL_ENABLED` | No | `false` | Set `true` to enable transactional email via SMTP |
| `MAIL_FROM_ADDRESS` | No | `no-reply@f29.us` | Sender address for outgoing mail |
| `MAIL_FROM_NAME` | No | `f29.us` | Sender display name |
| `MAIL_SMTP_HOST` | No | — | SMTP server hostname |
| `MAIL_SMTP_PORT` | No | `587` | SMTP port (587 = STARTTLS, 465 = SSL) |
| `MAIL_SMTP_ENCRYPTION` | No | `tls` | `tls` (STARTTLS) or `ssl` |
| `MAIL_SMTP_USERNAME` | No | — | SMTP authentication username |
| `MAIL_SMTP_PASSWORD` | No | — | SMTP authentication password |
| `MAIL_SUPPORT_ADDRESS` | No | *(falls back to `SUPPORT_EMAIL`)* | Support address included in security-alert notification emails |
| `MAIL_ADMIN_ADDRESS` | No | *(empty)* | Address to notify when a user submits a plan-change request; leave blank to disable |
| `STRIPE_ENABLED` | No | `false` | Set `true` to activate Stripe billing. When `false`, all Stripe paths are inactive. |
| `STRIPE_MODE` | No | `test` | `test` or `live`. Required to be one of these values when `STRIPE_ENABLED=true`. |
| `STRIPE_SECRET_KEY` | If enabled | — | Stripe secret key. Never logged or displayed in the ops page or browser output. Required when `STRIPE_ENABLED=true`. |
| `STRIPE_PUBLISHABLE_KEY` | If enabled | — | Stripe publishable key. Never displayed in full in the ops page — shown only as "configured" or "not set". Required when `STRIPE_ENABLED=true`. |
| `STRIPE_WEBHOOK_SECRET` | If enabled | — | Webhook signing secret. Never logged or displayed. Required when `STRIPE_ENABLED=true`. |
| `STRIPE_SUCCESS_URL` | If enabled | — | Redirect URL after successful checkout. Required when `STRIPE_ENABLED=true`. |
| `STRIPE_CANCEL_URL` | If enabled | — | Redirect URL when checkout is canceled. Required when `STRIPE_ENABLED=true`. |
| `STRIPE_CURRENCY` | No | `usd` | ISO 4217 lowercase currency code (e.g. `usd`). |

The application validates all required variables on startup. A missing or invalid variable causes an immediate 500 response (web) or an error message to STDERR with exit code 1 (CLI).

---

## Directory Structure

```
/app
  /Config             Application startup validators
  /Controllers        Route handlers (one class per controller)
  /Services           Business logic (auth, QR, slugs, analytics, CSRF, throttle, redirects)
  /Views              PHP view templates
    /auth             Login and register pages
    /errors           Error pages (404, 403, 500)
    /layouts          Shared layout wrappers
    /qr               QR code pages
    /redirect         Public redirect pages

/config
  app.php             Application configuration
  database.php        Database connection settings
  reserved_slugs.php  Slugs reserved for system routes

/database
  /migrations         Numbered PHP migration files
  /seeders            PHP seeder files

/public
  index.php           Front controller (single entry point)
  .htaccess           Apache rewrite rules

/storage
  /logs               PHP error log and cleanup log (git-ignored)

bootstrap.php         Loads .env, config, DB connection, core classes
migrate.php           CLI: run pending migrations
seed.php              CLI: run seeders
cleanup.php           CLI: prune old login_attempts rows (run via cron)
```

---

## Seeded Plans

| Plan | Internal name | QR codes | Analytics retention |
|------|--------------|----------|-------------------|
| Free | `free_v1` | 5 | 30 days |
| Starter | `starter_v1` | 50 | 90 days |
| Pro | `pro_v1` | 250 | 365 days |
| Team | `team_v1` | 1,000 | 365 days |

Pricing (cents) is `NULL` for paid plans until billing is configured.

---

## Routes

### Public

| Method | Path | Description |
|--------|------|-------------|
| GET | `/` | Homepage — hero, dynamic-QR value proposition, feature cards, static-vs-dynamic comparison, styling/downloads/analytics sections, pricing + final CTAs. Public; no DB writes; CTAs link to `/qr/create`, `/qr/static`, `/pricing`, `/help`, and `/register` (logged-out only). |
| GET | `/pricing` | Public plan comparison — features, prices, plan selection |
| GET | `/terms` | Terms of Service |
| GET | `/privacy` | Privacy Policy |
| GET | `/acceptable-use` | Acceptable Use Policy |
| GET | `/abuse` | Report Abuse page |
| GET | `/contact` | Contact page |
| GET | `/help` | Help Center — explains dynamic vs static QR, styling, downloads, analytics, plans, account, billing, moderation, security, and an FAQ |
| GET | `/verify-email` | Email verification link handler (token from email) |
| GET | `/forgot-password` | Forgot password form |
| POST | `/forgot-password` | Send password reset email |
| GET | `/reset-password` | Reset password form (token from email) |
| POST | `/reset-password` | Submit new password |
| GET | `/login` | Login form |
| POST | `/login` | Login submit |
| GET | `/register` | Register form |
| POST | `/register` | Register submit |
| POST | `/logout` | Logout |
| GET | `/{slug}` | **Public redirect** — resolves slug, logs scan, 302s to destination (catch-all, last priority) |

### Account

| Method | Path | Description |
|--------|------|-------------|
| GET | `/dashboard` | User dashboard |
| GET | `/account` | Redirects to `/account/settings` |
| GET | `/account/settings` | Account settings (profile, email, password) |
| POST | `/account/settings/profile` | Update profile fields (name, display name, company, phone, timezone) |
| POST | `/account/settings/email` | Request email address change |
| POST | `/account/settings/password` | Change password |
| GET | `/account/security` | Security page (sessions, login history) |
| GET | `/account/verify-email` | Email verification status / resend page |
| POST | `/account/verify-email/resend` | Resend verification email (60 s cooldown) |
| GET | `/account/subscription` | Current plan, usage summary, pending requests, request history, plan comparison |
| POST | `/account/subscription/change` | Plan change — free plan is immediate; paid plan creates a pending request (or is blocked if Stripe is enabled) |
| POST | `/account/subscription/checkout` | Start Stripe Checkout Session for a paid plan (STRIPE_ENABLED=true only) |
| POST | `/account/subscription/request-cancel` | Cancel a pending plan-change request |
| POST | `/account/subscription/cancel-stripe` | Cancel active Stripe subscription at period end (STRIPE_ENABLED=true only) |

### Stripe webhooks

| Method | Path | Description |
|--------|------|-------------|
| POST | `/stripe/webhook` | Stripe event ingestion — signature-verified, idempotent. No CSRF; authenticated via `Stripe-Signature` header. |

### QR codes

| Method | Path | Description |
|--------|------|-------------|
| GET | `/qr` | QR code list (search, status filter) |
| GET | `/qr/create` | Create QR form |
| POST | `/qr` | Create QR submit |
| GET | `/qr/{id}` | QR detail — info, SVG preview, copy URL, action buttons |
| GET | `/qr/{id}/edit` | Edit QR name and/or destination |
| POST | `/qr/{id}/update` | Save name and/or destination |
| POST | `/qr/{id}/pause` | Pause short link |
| POST | `/qr/{id}/resume` | Resume short link |
| POST | `/qr/{id}/archive` | Archive short link (stops redirecting) |
| POST | `/qr/{id}/restore` | Restore an archived link to active |
| POST | `/qr/{id}/destination-history/{historyId}/restore` | Restore a previous destination URL from history |
| GET | `/qr/{id}/download/png` | Download QR as PNG (requires GD extension) |
| GET | `/qr/{id}/download/svg` | Download QR as SVG |
| GET | `/qr/{id}/analytics` | Analytics page (date range, bot toggle, daily chart, device breakdown, referers) |
| GET | `/qr/{id}/analytics/export` | Export analytics as CSV (requires `can_export_analytics` entitlement) |
| GET | `/qr/{id}/style` | QR style page — color picker and logo upload |
| POST | `/qr/{id}/style` | Save custom foreground/background colors |
| POST | `/qr/{id}/style/reset` | Reset all style settings to default |
| POST | `/qr/{id}/style/logo` | Upload logo image (Pro/Team) |
| POST | `/qr/{id}/style/logo/remove` | Remove uploaded logo |

### Admin — users

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin` | Admin home — overview stats |
| GET | `/admin/users` | User list (email search, capped at 100) |
| GET | `/admin/users/{id}` | User detail — info, subscription history, entitlements, overrides |
| POST | `/admin/users/{id}/subscription` | Manually assign a plan and billing cycle |
| POST | `/admin/users/{id}/overrides` | Add or update a per-user feature override |
| POST | `/admin/users/{id}/overrides/{overrideId}/delete` | Delete a per-user feature override |

### Admin — plans

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/plans` | Plan catalog list |
| GET | `/admin/plans/create` | Create plan form |
| POST | `/admin/plans` | Create plan submit |
| GET | `/admin/plans/{id}` | Plan detail — info, features, billing prices, subscription counts |
| GET | `/admin/plans/{id}/edit` | Edit plan metadata |
| POST | `/admin/plans/{id}/update` | Save plan metadata |
| GET | `/admin/plans/{id}/clone` | Clone plan form |
| POST | `/admin/plans/{id}/clone` | Clone plan submit — copies all features into a new plan |
| POST | `/admin/plans/{id}/retire` | Retire plan — sets `is_public=0`, `is_legacy=1` |
| POST | `/admin/plans/{id}/features` | Add feature to plan |
| POST | `/admin/plans/{id}/features/{featureId}/update` | Update feature value/type |
| POST | `/admin/plans/{id}/features/{featureId}/delete` | Delete feature from plan |
| POST | `/admin/plans/{id}/billing-prices` | Add billing price mapping |
| POST | `/admin/plans/{id}/billing-prices/{priceId}/toggle` | Activate or deactivate a billing price mapping |

### Admin — subscription requests

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/subscription-requests` | Subscription change request list (status filter) |
| GET | `/admin/subscription-requests/{id}` | Request detail — user, current plan, requested plan, action buttons |
| POST | `/admin/subscription-requests/{id}/approve` | Approve — closes current subscription, creates new one |
| POST | `/admin/subscription-requests/{id}/deny` | Deny — marks request denied, subscription unchanged |
| POST | `/admin/subscription-requests/{id}/cancel` | Cancel — marks stale request canceled, subscription unchanged |

### Admin — audit logs, subscriptions, ops

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/audit-logs` | Audit log list (action, entity, user, date filters) |
| GET | `/admin/audit-logs/{id}` | Audit log entry detail with metadata JSON |
| GET | `/admin/subscriptions` | Subscription history — cross-user, filterable, capped at 100 |
| GET | `/admin/ops` | System health — PHP version, extensions, DB counters, mail config, login activity |
| POST | `/admin/ops/send-test-email` | Send a test email to verify SMTP delivery (admin only, CSRF-protected) |

### Admin — moderation

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/moderation/links` | Moderated links — filter by status, owner, slug, destination |
| GET | `/admin/moderation/links/{id}` | Link detail — owner, destination, moderation metadata, scan count |
| POST | `/admin/moderation/links/{id}/disable` | Disable a link — stops redirects immediately |
| POST | `/admin/moderation/links/{id}/restore` | Restore a disabled link to active |
| GET | `/admin/moderation/domains` | Blocked domain list with add form |
| POST | `/admin/moderation/domains` | Add a domain to the blocklist |
| POST | `/admin/moderation/domains/{id}/toggle` | Activate or deactivate a blocked domain entry |

---

## Testing Auth Locally

After setup, you can verify the auth layer works:

1. Visit `http://localhost:8000/register` — create an account
2. You will be logged in and redirected to `/dashboard`
3. Visit `http://localhost:8000/logout` in the nav — you will be sent to `/login`
4. Visit `http://localhost:8000/dashboard` while logged out — redirects to `/login`
5. Log back in with your credentials

To test suspended accounts, run in MySQL:
```sql
UPDATE users SET status = 'suspended' WHERE email = 'you@example.com';
```
Then attempt to log in — you should see the suspension message.

---

## Services

### EntitlementService
Resolves a user's effective plan features with per-user override support. Resolution order: active non-expired `user_feature_overrides` → active subscription `plan_features` → caller fallback → null. Values are typed (int / bool / string).

```php
EntitlementService::isEnabled($userId, 'can_export_svg');          // bool
EntitlementService::getValue($userId, 'max_qr_codes', 0);          // int
EntitlementService::getAllForUser($userId);                          // full map
```

### SlugService
Validates and generates short-link slugs. Enforces the reserved-slug list, pattern `^[a-z0-9]+(?:-[a-z0-9]+)*$`, plan-specific min/max length, and uniqueness against `short_links`.

```php
SlugService::validateCustomSlugForUser($userId, 'my-link');        // ['valid', 'slug', 'errors']
SlugService::generateUniqueSlug();                                  // e.g. "k4x9mz"
SlugService::validateFormat('hello', minLength: 4, maxLength: 32); // ['valid', 'errors']
```

### RedirectService
Resolves public slug requests, logs scan events, and performs 302 redirects. Paused/disabled links show an unavailable page without logging.

```php
RedirectService::handleSlug('my-slug');  // never returns; redirects or renders
```

Scan events include: SHA-256 IP hash, user agent (truncated to 1000 chars), referer (truncated to 2000 chars), device type heuristic (mobile/tablet/desktop/unknown), and bot flag heuristic. Geolocation fields (`country_code`, `region`, `city`) are stored as NULL in this version.

### AnalyticsService
Aggregates scan event data for the analytics page. All queries are scoped to a `short_link_id` and an explicit date range.

```php
AnalyticsService::getTotalScans($shortLinkId, $fromDate, $toDate, $includeBots = false);      // int
AnalyticsService::getBotCount($shortLinkId, $fromDate, $toDate);                              // int
AnalyticsService::getDailyCounts($shortLinkId, $fromDate, $toDate, $includeBots = false);     // [['scan_date', 'total'], ...]
AnalyticsService::getDeviceBreakdown($shortLinkId, $fromDate, $toDate, $includeBots = false); // [['device_type', 'total'], ...]
AnalyticsService::getTopReferers($shortLinkId, $fromDate, $toDate, $includeBots = false);     // top 10 referers
AnalyticsService::getExportRows($shortLinkId, $fromDate, $toDate, $includeBots = false);      // raw rows for CSV export
```

`$fromDate` and `$toDate` are `'Y-m-d'` strings. The controller resolves the `analytics_retention_days` plan feature into clamped date strings before calling the service; the service has no knowledge of retention days.

**Retention note:** `analytics_retention_days` is a query visibility rule, not a data purge. Older rows remain in `scan_events`; they fall outside the date range the controller passes in.

### CsrfService
Generates and validates synchronizer tokens for all state-changing forms.

```php
CsrfService::field();        // renders hidden <input> with the session token
CsrfService::requireValid(); // halts with 403 if token missing or wrong
CsrfService::refresh();      // discards current token (call after login/register)
```

Token is stored as `$_SESSION['csrf_token']` and persists for the session lifetime. `hash_equals` is used on comparison to prevent timing attacks. The token is rotated (refreshed) immediately after a successful login or registration so the pre-auth token cannot be reused.

### LoginThrottleService
Database-backed login throttling stored in `login_attempts`. Survives cleared cookies and new browser sessions.

```
Policy: 5 failed attempts per email  within 15 min → lockout
        20 failed attempts per IP hash within 15 min → lockout
Both checks applied; either triggers a 15-minute block.
```

```php
LoginThrottleService::isLockedOut($email, $ipHash); // bool
LoginThrottleService::record($email, $ipHash, $success); // persists one row
LoginThrottleService::hashIp($remoteAddr);           // ?string HMAC-SHA256 or null
```

---

## Security

### CSRF protection
All POST endpoints (login, register, logout, QR create/update/pause/resume) validate the synchronizer token via `CsrfService::requireValid()`. Missing or mismatched tokens return a 403 page. The token is rotated after every successful login or registration.

### Session hardening
- `AuthService::requireAuth()` verifies the session user still exists in the database and is not suspended on every protected request. Stale sessions are cleared and redirected to `/login`.
- Guest pages (`/login`, `/register`) use `currentUser()` rather than raw session presence, so a stale session does not silently redirect a user away from the login page.
- `AuthService::logout()` uses the array-form `setcookie()` to expire the session cookie with all original security attributes including `SameSite=Lax`.
- `storage/logs/` is created automatically by `bootstrap.php` if absent, preventing silent log failures on fresh deployments.

### Login throttle
Database-backed via `login_attempts` table. **5 failed attempts per email within 15 minutes** triggers a lockout; **20 failed attempts per IP** within the same window triggers a lockout. Lockout is checked before credentials are verified and survives cleared cookies or new sessions. No external infrastructure required.

IPs are stored as **HMAC-SHA256** (`hash_hmac('sha256', $ip, APP_KEY)`) — a plain SHA-256 hash is reversible against a known IP space; HMAC with a secret key is not. `APP_KEY` **must** be set in `.env`; the application throws explicitly if it is missing rather than falling back to an insecure hash.

Composite indexes `(email_normalized, success_flag, attempted_at)` and `(ip_hash, success_flag, attempted_at)` cover the throttle queries exactly. A single-column `(attempted_at)` index covers the cleanup query.

#### Cleanup

Old attempt rows accumulate forever without cleanup. Run periodically:

```bash
php cleanup.php
```

Deletes rows older than **90 days** from `login_attempts`. Suggested cron (daily at 03:00):

```
0 3 * * * php /path/to/f29.us/cleanup.php >> /path/to/storage/logs/cleanup.log 2>&1
```

### Input limits
- QR name: maximum 200 characters (`mb_strlen`)
- Destination URL: maximum 2048 characters
- Both limits enforced server-side and reflected in form `maxlength` attributes

### URL safety
All destination URLs are validated for `http`/`https` scheme and the absence of control characters (`\r`, `\n`, `\t`, `\0`) before write. The redirect service re-validates scheme on read and strips control characters immediately before setting the `Location` header.

### Response headers (applied to all web responses)
```
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self' https://checkout.stripe.com; object-src 'none'
```

**CSP notes:**
- `script-src 'self'` — no inline JS in any view; all JS lives in `public/assets/js/app.js`; DOM-API style mutation (`el.style.width`) in app.js is not controlled by `style-src`
- `style-src 'self'` — no `'unsafe-inline'`; no `style=` attributes or `<style>` blocks anywhere in views or the layout; all styling uses utility classes from `public/assets/css/app.css`
- `img-src 'self' data:` — QR code SVG previews are rendered as `data:image/svg+xml;base64,…` in `<img src>`; no external image CDNs
- `frame-ancestors 'self'` — permits same-origin framing only; stricter than `X-Frame-Options: SAMEORIGIN` which is also sent for older browser compatibility
- `base-uri 'self'` — prevents `<base>` tag injection attacks
- `form-action 'self' https://checkout.stripe.com` — form submissions target the same origin; `https://checkout.stripe.com` is added to permit browser navigation after the Stripe Checkout form-submission redirect (the checkout POST returns a 302 to Stripe's hosted page)
- `object-src 'none'` — disallows Flash and legacy plugin content

**Static assets:**
- `public/assets/css/app.css` — all application CSS; no external CSS CDNs
- `public/assets/js/app.js` — all application JavaScript; handles `data-confirm` form confirmations, `data-copy-target` clipboard copy, `data-bar-pct` bar chart widths, and `data-submit-form` link-to-form submission

### Download error handling
QR library failures (e.g. `composer install` not yet run) are caught, logged server-side, and return a safe 403 message rather than leaking stack traces.

---

## Operational Commands

Quick reference for the most common CLI operations.

```bash
# Install production dependencies
composer install --no-dev --optimize-autoloader

# Run pending database migrations (idempotent — safe to re-run)
php migrate.php

# Seed plan catalog (idempotent — uses ON DUPLICATE KEY UPDATE)
php seed.php

# Prune login_attempts rows older than 90 days
php cleanup.php

# Start local dev server
php -S localhost:8000 -t public
```

**Suggested cron job** — daily cleanup at 03:00 (cPanel Cron Jobs or crontab):

```
0 3 * * * php /home/youruser/f29us/cleanup.php >> /home/youruser/f29us/storage/logs/cleanup.log 2>&1
```

---

## Troubleshooting

### "Configuration error: APP_KEY is required"

The application validates required environment variables on startup. Generate `APP_KEY`:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Add the output as `APP_KEY` in `.env`. The same command works for any required variable — check the startup error message for which one is missing.

### Site shows a blank page or 500 error

1. Temporarily set `APP_DEBUG=true` in `.env` to display the error in the browser (local dev only — never leave this on in production).
2. Check `storage/logs/error.log` for PHP error messages.
3. Verify all required environment variables are set (see [Environment Variables](#environment-variables) above).

### PNG downloads fail or show an error

The PHP GD extension is required for PNG QR generation. Check:

```bash
php -m | grep -i gd
```

On cPanel, enable GD via **MultiPHP INI Editor** → select your PHP version → enable `extension=gd`. On a VPS: `apt install php8.2-gd` (adjust version), then restart the web server.

### "Class not found" or autoload errors

Composer dependencies are not installed. Run:

```bash
composer install --no-dev
```

If Composer is not available on the server, run `composer install --no-dev` locally and upload the `vendor/` directory.

### Sessions not persisting / always redirected to login

1. Confirm `session.save_path` is writable by the web server process.
2. Verify your browser is not blocking cookies. The session cookie is named `f29_sess`.
3. On some shared hosts, the default session directory fills up — configure a custom `session.save_path` in `php.ini`.

### `storage/logs/` not writable

`bootstrap.php` creates `storage/logs/` automatically on first request. If the directory exists but errors are not being logged:

```bash
chmod 755 storage/logs
```

---

## Admin

### Role column

Migration `012_add_role_to_users` adds a `role ENUM('user','admin') NOT NULL DEFAULT 'user'` column to the `users` table. All existing users default to `'user'`.

### Promoting the first admin

There is no UI for role management. Promote a user directly in the database:

```sql
UPDATE users SET role = 'admin' WHERE email = 'you@example.com';
```

### Admin tooling

Admins have access to an internal-only area at `/admin` (yellow **Admin** link in the nav). This area provides:

| Page | Path | Description |
|---|---|---|
| Admin home | `/admin` | Overview stats: users, QR codes, plans, active subscriptions, pending requests, 24 h audit events, 24 h failed logins |
| User list | `/admin/users` | Searchable list of all users (capped at 100), with role, status, and active plan |
| User detail | `/admin/users/{id}` | Full user info, subscription history, effective entitlements with source (plan vs override), and active overrides |
| Change subscription | `POST /admin/users/{id}/subscription` | Manually assign a plan, billing cycle, and optional grandfathered flag. Cancels the current active subscription and creates a new one. |
| Add / update override | `POST /admin/users/{id}/overrides` | Set a per-user feature override (int, bool, or string) with optional expiry and note. Upserts on `(user_id, feature_key)`. |
| Delete override | `POST /admin/users/{id}/overrides/{id}/delete` | Remove a specific override for a user. |
| Plan list | `/admin/plans` | All plans with flags (public, active, legacy), sort order, and feature count. |
| Plan detail | `/admin/plans/{id}` | Full plan info, subscription counts, feature list with inline edit, and add-feature form. |
| Create plan | `/admin/plans/create` | Create a new plan with metadata and flags. |
| Edit plan | `/admin/plans/{id}/edit` | Update display name, description, prices, currency, flags, and sort order. `internal_name` is read-only after creation. |
| Add feature | `POST /admin/plans/{id}/features` | Add a new feature key/value to a plan. Feature key is fixed after creation. Built-in keys enforce their value type. |
| Update feature | `POST /admin/plans/{id}/features/{featureId}/update` | Change a feature's value and/or value type. Built-in keys enforce their value type. |
| Delete feature | `POST /admin/plans/{id}/features/{featureId}/delete` | Remove a feature from a plan. |
| Clone plan | `/admin/plans/{id}/clone` | Clone a plan and all its features into a new plan with a new unique internal name. Source plan is not modified. |
| Retire plan | `POST /admin/plans/{id}/retire` | Set `is_public=0`, `is_legacy=1`, `is_active=1`. Removes the plan from sale while preserving existing subscribers. |
| Add billing price | `POST /admin/plans/{id}/billing-prices` | Map a plan to a payment provider's price ID (e.g. a Stripe price ID). `amount_cents` is optional. No payment is processed — informational groundwork only. |
| Toggle billing price | `POST /admin/plans/{id}/billing-prices/{priceId}/toggle` | Activate or deactivate a billing price mapping. |
| Subscription request list | `/admin/subscription-requests` | All subscription change requests with status filter (pending / approved / denied / canceled / all). |
| Subscription request detail | `/admin/subscription-requests/{id}` | Full request context: user, current plan, requested plan flags, action buttons. |
| Approve request | `POST /admin/subscription-requests/{id}/approve` | Transactionally closes current subscription and creates new active subscription for the requested plan. Marks request approved. |
| Deny request | `POST /admin/subscription-requests/{id}/deny` | Marks request denied with optional note. User's subscription is unchanged. |
| Cancel request (admin) | `POST /admin/subscription-requests/{id}/cancel` | Marks stale or erroneous pending request as canceled. User's subscription is unchanged. |
| Subscription history | `/admin/subscriptions` | All user subscriptions with filters: user email, plan, status, billing cycle, date range. Capped at 100. Links to user and plan detail. |
| Audit log list | `/admin/audit-logs` | Browse all audit log entries. Filterable by action, entity type, user email, and date range. Capped at 100. |
| Audit log detail | `/admin/audit-logs/{id}` | Full audit entry: acting user, entity, action, pretty-printed metadata JSON. Related links to user, plan, or subscription request where applicable. |
| Operations | `/admin/ops` | System health snapshot: environment, PHP version, extensions (GD, mbstring), filesystem checks, migration count, database counters, login activity (24 h), Stripe configuration readiness. Send Test Email form to verify SMTP delivery. |

All admin POST endpoints are CSRF-protected and require the admin role. Non-admins receive a 403. All subscription changes, override operations, and plan catalog changes are written to `audit_logs`.

### Plan catalog: key rules

- **`internal_name` is immutable.** Set it carefully on creation — it is the stable identifier used in code and audit logs. The edit page shows it read-only. There is no rename endpoint.
- **Feature keys are fixed after creation.** Only value and value type can be updated.
- **Bool feature values** must be the literal string `"true"` or `"false"` — this matches the `EntitlementService::castValue()` contract.
- **`is_active`** controls whether the plan can be assigned to users. Inactive plans are excluded from the subscription change dropdown.
- **`is_legacy`** marks grandfathered plans. Legacy plans remain fully functional for current subscribers; they are simply hidden from public-facing catalog pages (once those exist) and flagged in the admin list.
- **`is_public`** controls visibility in a future public plan catalog. It has no current effect on access or entitlements.
- **Built-in feature keys** (`max_qr_codes`, `can_export_png`, etc.) have a required value type enforced server-side. Submitting the wrong type is rejected.

### Plan versioning and grandfathering workflow

The recommended workflow for evolving a plan without disrupting existing subscribers:

1. **Clone** the current plan into a new version (e.g., `starter_v1` → `starter_v2`) via the Clone button on the plan detail page.
2. **Adjust** the new plan's features and metadata as needed.
3. **Retire** the old plan (sets `is_public=0`, `is_legacy=1`, `is_active=1`) so it is no longer offered to new users but continues to work for current subscribers.
4. **Assign** users to the new plan manually when intended — existing subscribers on the retired plan are not moved automatically.

The plan detail and edit pages show a warning banner when the plan has active subscribers, and include guidance on when to edit vs. clone.

### Billing / public subscriptions

Stripe Checkout and subscription lifecycle management are implemented (Phases 35–40). Set `STRIPE_ENABLED=true` and configure Stripe env vars to activate billing. Each billing price mapping carries a `provider_mode` (`test`/`live`) — checkout only uses mappings matching the current `STRIPE_MODE`, preventing test/live cross-contamination. Monthly and yearly Subscribe buttons are shown independently per active mapping. Admin plan assignment remains available as a manual fallback for complimentary and grandfathered access. See [docs/STRIPE_PLAN.md](docs/STRIPE_PLAN.md) for the full Stripe architecture and test-mode validation procedure. See [docs/STRIPE_LAUNCH_CHECKLIST.md](docs/STRIPE_LAUNCH_CHECKLIST.md) before switching to live mode.

---

## What Is Implemented

| Feature | Status |
|---|---|
| Register / Login / Logout | ✓ |
| Default Free plan assignment | ✓ |
| Entitlement resolution (plan features + per-user overrides) | ✓ |
| Slug validation and auto-generation | ✓ |
| QR code creation (name, destination, optional custom slug) | ✓ |
| QR code list page | ✓ |
| QR code detail page | ✓ |
| Edit QR code name (always available) | ✓ |
| Edit destination URL (plan-gated) | ✓ |
| Pause / Resume short link | ✓ |
| Archive / Restore short link | ✓ |
| **Destination history** — every destination change recorded | ✓ |
| **Destination restore** — revert to any previous URL from history | ✓ |
| **Analytics date range filter** (from/to, clamped to plan retention) | ✓ |
| **Analytics bot toggle** (exclude or include bot traffic) | ✓ |
| **Analytics CSV export** (plan-gated via `can_export_analytics`) | ✓ |
| QR search and status filter on list page | ✓ |
| QR preview (SVG, in-app display) | ✓ |
| Copy short URL button (one-click, no library) | ✓ |
| Download PNG with prefixed filename (`f29-qr-{name}-{slug}.png`) | ✓ |
| Download SVG with prefixed filename (`f29-qr-{name}-{slug}.svg`) | ✓ |
| Audit logging (create, edit, pause, resume, archive, restore) | ✓ |
| **Public slug redirect (`/{slug}` → destination, HTTP 302)** | ✓ |
| **Unavailable page for paused/disabled/archived links** | ✓ |
| **Scan event logging on redirect** | ✓ |
| **Analytics page (`/qr/{id}/analytics`)** | ✓ |
| **Plan-based analytics retention (visibility rule)** | ✓ |
| **CSRF protection on all POST endpoints** | ✓ |
| **Session hardening (stale user ejection)** | ✓ |
| **Login throttle (5 attempts → 15 min lockout, database-backed)** | ✓ |
| **Input length limits (name 200, URL 2048)** | ✓ |
| **URL header-injection defense** | ✓ |
| **Security response headers (X-Frame-Options, nosniff, Referrer-Policy, CSP)** | ✓ |
| **Download failure safe error page** | ✓ |
| **Startup config validation (required env vars checked on boot)** | ✓ |
| **Global exception handler (500 page in production, stack trace in debug)** | ✓ |
| **CLI guard on migrate.php, seed.php, cleanup.php** | ✓ |
| **Admin role column + `AuthService::isAdmin()`** | ✓ |
| **Admin user list with email search** | ✓ |
| **Admin user detail (info, subscription history, entitlements, overrides)** | ✓ |
| **Admin: manual plan assignment (cancel old, create new)** | ✓ |
| **Admin: per-user feature override add / update / delete** | ✓ |
| **Audit logging for admin subscription and override changes** | ✓ |
| **Admin: plan catalog list with flags and feature counts** | ✓ |
| **Admin: create plan (internal_name immutable after creation)** | ✓ |
| **Admin: edit plan metadata, flags (public/active/legacy), sort order** | ✓ |
| **Admin: add / update / delete plan features (inline edit, no JS required)** | ✓ |
| **Audit logging for plan and feature changes (created, updated diff, added, updated, deleted)** | ✓ |
| **Admin: plan clone / versioning helper (transactional, copies all features)** | ✓ |
| **Admin: plan retire helper (is_public=0, is_legacy=1, is_active=1)** | ✓ |
| **Admin: active-subscriber warning on plan detail and edit pages** | ✓ |
| **Admin: built-in feature key registry with server-side value-type enforcement** | ✓ |
| **Admin: built-in key badges in plan feature table** | ✓ |
| **Public pricing page (`/pricing`) with plan comparison** | ✓ |
| **Account subscription page — lifecycle status, usage summary, plan comparison** | ✓ |
| **Account subscription page — pending requests section with cancel** | ✓ |
| **Account subscription page — resolved request history (last 10)** | ✓ |
| **Self-service switch to free plan (immediate, transactional)** | ✓ |
| **Self-service request for paid plan (creates pending request, no charge)** | ✓ |
| **`subscription_change_requests` table for admin review** | ✓ |
| **Cancel a pending change request (user-side)** | ✓ |
| **Audit logging for self-service plan changes and requests** | ✓ |
| **Admin subscription request review (list, detail, approve, deny, cancel)** | ✓ |
| **Admin audit log browser (list with filters, detail with metadata + related links)** | ✓ |
| **Admin subscription history (cross-user view with filters)** | ✓ |
| **Admin operations page (system health, DB counters, login activity)** | ✓ |
| **Billing state schema on `user_subscriptions` (provider fields, billing_status, period dates)** | ✓ |
| **`plan_billing_prices` table — maps plans to provider price IDs** | ✓ |
| **Admin: billing price mapping UI on plan detail (add, activate/deactivate, health warning)** | ✓ |
| **Admin: billing state columns in user detail and subscription history** | ✓ |
| **Transactional email foundation — PHPMailer SMTP, `MailerService`, `NotificationService`** | ✓ |
| **Email notifications: subscription request submitted / approved / denied / canceled** | ✓ |
| **Email notifications: email address changed (old + new), password changed** | ✓ |
| **Email notifications: link disabled by admin, link restored by admin** | ✓ |
| **Admin ops page — mail configuration section (enabled status, PHPMailer check, SMTP host)** | ✓ |
| **User profile fields — first/last name, display name, company, phone, timezone** | ✓ |
| **Account settings profile form — update profile, CSRF-protected, audit-logged** | ✓ |
| **Display name resolution — display_name → first + last → email (nav, dashboard, settings)** | ✓ |
| **Admin user detail — profile fields shown read-only** | ✓ |
| **Email verification — token-based, 24 h expiry, SHA-256 hashed, single-use** | ✓ |
| **Registration verification — email sent on signup; banner + `/account/verify-email` page** | ✓ |
| **Resend verification email — 60-second cooldown, audit-logged** | ✓ |
| **Email change confirmation — deferred flow; new address confirmed before update applies** | ✓ |
| **Email change security notice — old address notified when change is requested** | ✓ |
| **Verified email enforcement — QR create, destination edit, destination restore, paid plan request** | ✓ |
| **Verification status in account settings and admin user detail** | ✓ |
| **Password reset by email — forgot-password form, reset link, single-use hashed token** | ✓ |
| **Password reset — non-enumerating (same response for known and unknown emails)** | ✓ |
| **Password reset — 60-minute expiry, SHA-256 hashed, concurrency-safe (FOR UPDATE)** | ✓ |
| **Password reset — security notification emails on request and on completion** | ✓ |
| **Stripe SDK (`stripe/stripe-php`) added to Composer dependencies** | ✓ |
| **`StripeService` — `isEnabled()`, `mode()`, `currency()`, `clientReady()`, `requireEnabled()`** | ✓ |
| **Migration 027 — `stripe_customer_id` column on `users` (user-level Stripe customer reference)** | ✓ |
| **Stripe env vars in `.env.example` — 8 vars including keys, mode, URLs, currency** | ✓ |
| **Config validation — required Stripe vars validated on startup when `STRIPE_ENABLED=true`** | ✓ |
| **Admin ops Stripe section — SDK presence, key config status, price mapping counts, plan coverage** | ✓ |
| **Migration 028 — `stripe_checkout_sessions` table (pending/completed/expired/canceled)** | ✓ |
| **`StripeService::getOrCreateCustomerForUser()` — create or reuse Stripe customer, persist to `users.stripe_customer_id`** | ✓ |
| **`StripeService::createCheckoutSession()` — server-side plan/price validation, Stripe Checkout Session creation, local row insert** | ✓ |
| **`POST /account/subscription/checkout` — CSRF, auth, verified email, server-side lookup, redirect to Stripe** | ✓ |
| **Subscription page: Subscribe button (Stripe) vs Request Review (manual) vs disabled when no price configured** | ✓ |
| **Pricing page: Subscribe/Request Review/Not available buttons based on Stripe status** | ✓ |
| **Checkout return URL handling: `?checkout=success` and `?checkout=canceled` — info messages only, no access granted** | ✓ |
| **Paid plan request blocked via `POST /account/subscription/change` when Stripe is enabled** | ✓ |
| **Migration 029 — `stripe_webhook_events` table (idempotent event recording, UNIQUE on stripe_event_id)** | ✓ |
| **`StripeService::constructWebhookEvent()` — Stripe-Signature header verification via Stripe SDK** | ✓ |
| **`StripeService::retrieveSubscription()` — retrieve live Stripe Subscription object by ID** | ✓ |
| **`StripeWebhookService` — idempotent event recording, event routing, mark processed/failed/ignored** | ✓ |
| **`checkout.session.completed` handler — activates `user_subscriptions`, maps billing_status, clears entitlement cache** | ✓ |
| **`checkout.session.expired` handler — marks local checkout session expired** | ✓ |
| **`POST /stripe/webhook` endpoint — raw body read, signature verified, 400 on failure, 200 after recording** | ✓ |
| **Admin ops webhook stats — total event count, latest processed timestamp, failed/ignored counts (24 h)** | ✓ |
| **`StripeService::cancelSubscriptionAtPeriodEnd()`, `mapSubscriptionStatus()`, `stripeTimestampToSql()`** | ✓ |
| **`customer.subscription.updated/deleted` webhook handlers — sync billing_status, period dates, entitlement cache** | ✓ |
| **`invoice.payment_succeeded/failed` webhook handlers — sync active/past_due status and period dates** | ✓ |
| **`POST /account/subscription/cancel-stripe` — cancel-at-period-end via Stripe API, optimistic local update** | ✓ |
| **`EntitlementService` billing-status gating — not_applicable/manual/active/trialing/past_due → paid plan; canceled+future period → paid plan; else → Free plan** | ✓ |
| **`BillingStatusService` — `bannerForSubscription()`, `isStripeBacked()`, `isAccessCurrentlyPaid()`** | ✓ |
| **Billing banners on subscription page and dashboard (info for cancel-scheduled/future access; error for payment failure/access ended)** | ✓ |
| **Billing status + period-end/renews-on rows in subscription page Current Plan table** | ✓ |
| **Cancel subscription button on subscription page (Stripe-backed only, conditional on state)** | ✓ |
| **Notification emails: `paymentFailed()`, `subscriptionCancellationScheduled()`, `subscriptionCanceled()`** | ✓ |
| **Admin Ops: Subscription Billing State section (active/trialing/past_due/unpaid/incomplete/cancel_soon counts)** | ✓ |

## Subscription Groundwork

### Public pricing page

`/pricing` is publicly accessible. It shows all plans with `is_public = 1`, `is_active = 1`, `is_legacy = 0`, ordered by `sort_order`. Authenticated users see which plan they are currently on and which plans have pending requests.

### Account subscription page

`/account/subscription` is accessible to authenticated users. It shows:

- **Current plan** — plan name, billing cycle, billing status, period end (renews on / access until), started date, grandfathered date if set, legacy badge if applicable, and a billing state banner when relevant
- **Cancel Subscription** — shown for active Stripe-backed subscriptions that are not already scheduled to cancel
- **Usage summary** — QR code count vs. limit, analytics retention days, custom slug and SVG export availability (drawn from live entitlements)
- **Pending plan-change requests** — any waiting requests with cancel option; shows "none" state when empty
- **Recent request history** — last 10 resolved (approved/denied/canceled) requests with outcome messaging
- **Available plans** — feature comparison table. Action labels per plan column:
  - `Current Plan` — already on this plan
  - `Request Pending` — pending change request exists for this plan
  - `Subscribe` — paid plan with active Stripe price (Stripe enabled)
  - `Online checkout not configured` — paid plan with no active Stripe price
  - `Cancel paid subscription above` — Free plan column when user has an active Stripe-backed paid subscription
  - `Switch to Free` — Free plan column for non-Stripe-backed subscriptions
  - `Request Review` — paid plan when Stripe is disabled (manual flow)

### Plan selection behavior

Switching to a plan is handled server-side. All plan eligibility is validated server-side (public, active, not legacy) — the posted `plan_id` is always re-verified against the database.

- **Free plan — Stripe-backed subscription active**: blocked with an error. User must use the Cancel Subscription button (cancel-at-period-end via Stripe API) instead of locally switching to Free. This prevents a mismatch where Stripe continues billing while the local subscription is set to Free.
- **Free plan — non-Stripe subscription**: the switch is immediate. The current subscription is closed and a new active free subscription is created.
- **Paid plan — Stripe enabled**: must use Stripe Checkout (`POST /account/subscription/checkout`). Direct POST to `/account/subscription/change` for a paid plan is rejected when `STRIPE_ENABLED=true`.
- **Paid plan — Stripe disabled**: a `subscription_change_requests` row is created with `status='pending'` for admin review. No subscription change occurs until approved.

### Admin review of change requests

The `subscription_change_requests` table stores all requests with `current_plan_id`, `requested_plan_id`, `status`, `requested_at`, `reviewed_at`, and `note`. Admins can list, inspect, approve, deny, or cancel requests at `/admin/subscription-requests`. Approval is transactional: it cancels the current active subscription and creates a new one for the requested plan.

## Moderation

### Link status distinction

| Status | Set by | Meaning |
|--------|--------|---------|
| `active` | User or admin | Link redirects normally |
| `paused` | User | User has temporarily paused redirects |
| `archived` | User | User has archived the link; redirects stop |
| `disabled` | Admin only | Admin/moderation action; redirects stop |

Users can pause, resume, archive, and restore their own links. Users **cannot** resume or restore a `disabled` link. Only admins can disable and restore links via the moderation panel.

### QR quota policy

The `max_qr_codes` entitlement is enforced against **countable** QR codes only. Archived QR codes do not count toward the limit, allowing users to retire printed materials without losing history or consuming plan capacity.

| Status | Counts toward `max_qr_codes` |
|--------|------------------------------|
| `active` | Yes |
| `paused` | Yes |
| `disabled` | Yes |
| `archived` | **No** |

**Restore requires capacity.** Restoring an archived QR code transitions it to `active`, so the user must have available quota. The server blocks restore if the limit is reached; the detail page also disables the Restore button when the user is at capacity.

**Slug reservation.** Archived slugs remain reserved in `short_links` and are not reused for new QR codes.

**No hard deletion.** QR codes, slugs, analytics, audit history, and destination history are never permanently deleted. Archive is the only retire action.

### QR color customization

Users on Starter and higher plans can customize the foreground (dot) color, background color, and background transparency of their QR codes via `/qr/{id}/style`. The style is stored in `qr_code_styles` (one row per QR, CASCADE DELETE from `qr_codes`). QR codes with no style row use black-on-white defaults.

**Entitlement key:** `can_customize_qr_colors` (bool). The style page is accessible to all users but shows an upgrade card and a disabled form for users without the entitlement. POST to `/qr/{id}/style` and `/qr/{id}/style/reset` enforce the entitlement server-side with a 403.

**Transparent background:** When `background_transparent` is enabled, both PNG and SVG outputs render the background as fully transparent. The stored background color is still validated against the foreground for contrast (it serves as a contrast reference for physical print). Transparent QR codes should be placed on a high-contrast, light-colored background.

**Color validation** (`QrStyleService::validateColors`):
- Both colors must be valid 6-digit hex strings (`#RRGGBB`)
- Foreground and background must differ
- WCAG contrast ratio must be ≥ 3.0 — computed as `(L1 + 0.05) / (L2 + 0.05)` using the linearized sRGB relative luminance formula
- Validation runs regardless of whether transparent background is enabled

**Error correction level policy:**
- Default style (no custom row): ECL = M (library default)
- Custom colors or transparent background: ECL = Q (increased resilience for colored/transparent modules)
- Logo overlay uses ECL = H

**`QrStyleService` methods:**
```php
QrStyleService::getForQr(int $qrId): array                                         // load style or default
QrStyleService::saveColors(int $qrId, string $fg, string $bg, bool $transparent = false, string $moduleStyle = 'square'): void  // upsert, sets ECL=Q
QrStyleService::reset(int $qrId): void                                             // DELETE row → reverts to default
QrStyleService::validateColors(string $fg, string $bg): array  // returns error strings
QrStyleService::validateModuleStyle(string $style): ?string    // null if valid, else error string
QrStyleService::normalizeHexColor(string $color): ?string      // #RRGGBB uppercase or null
QrStyleService::contrastRatio(string $fg, string $bg): float   // WCAG ratio
```

### QR module style

Users on Starter and higher plans can also customize the **shape** of QR data modules: classic squares, gapped squares (modern look with spacing), or circles (rounded dot style). The selection is stored in the `module_style` column on `qr_code_styles` and applies to both PNG and SVG output.

**Allowed values for `module_style`:** `square` (default), `gapped_square`, `circle`.

**Entitlement key:** `can_customize_qr_module_style` (bool). Free = false; Starter / Pro / Team = true. The Module Style control on the style page is disabled for users without the entitlement, and server-side POST with `module_style != square` is rejected with 403 to prevent hidden-form tampering.

**Finder-pattern preservation:** For scan reliability, the three finder-pattern blocks (top-left, top-right, bottom-left 7x7 modules) are always rendered as classic full squares, regardless of the selected module style. Only normal data modules pick up the new shape.

**Error correction policy with module style:**
- Default style (no custom row, all defaults including `module_style = square`): ECL = M
- Custom colors, transparent background, **or non-square `module_style`**: ECL = Q
- Logo overlay: ECL = H (logo wins; non-square module style does not demote H to Q)

**Renderer:**
- `module_style = 'square'` uses the standard endroid writer path before the final exact-size PNG normalization. SVG output for square style is the endroid writer output unchanged. PNG output for square style is no longer byte-identical to the pre–Phase 33 renderer because of the exact-size resize step, but it still uses the standard endroid render path for all module rendering.
- `module_style = 'gapped_square' | 'circle'` walks the endroid Matrix and emits per-module shapes: a centered rect at 80% of module size for gapped, a centered filled ellipse at 80% diameter for circle. Finder-pattern modules render as full squares regardless. Logo overlay (if any) is composited last.

**Supported output surfaces:** in-app SVG preview, PNG download, SVG download — all reflect the saved module style.

### QR PNG download size

Users can pick the pixel size of the PNG export at download time on the QR detail page. The selection is an **export option**, not a persistent style — the stored QR style is unchanged. SVG remains vector and is not size-gated.

**Entitlement key:** `max_qr_download_size_px` (int). Seeded values:

| Plan    | `max_qr_download_size_px` |
| ------- | ------------------------: |
| Free    |                       512 |
| Starter |                      1024 |
| Pro     |                      2048 |
| Team    |                      4096 |

**Fixed allowed sizes:** `[512, 1024, 2048, 4096]`. The detail page's PNG size selector shows only sizes `≤ max_qr_download_size_px`. The default selection is the largest allowed size. The selector submits as `GET /qr/{id}/download/png?size={px}`.

**Server-side validation** (`QrController::downloadPng`):
- `size` must be a digit string and present in the fixed allow-list → otherwise `403 Invalid PNG download size.`
- `size` must be `≤ max_qr_download_size_px` for the user → otherwise `403 Your plan does not allow that PNG download size.`
- Missing/blank `size` defaults to `512`.

**Rendering:** the selected size is passed through to `QrCodeService::generatePng($content, $size, $style)`. The default (square) path uses endroid's writer at the requested size; the custom matrix renderer (for `gapped_square` / `circle`) renders at the matrix's natural pixel size. In both paths a final GD nearest-neighbor resize (`imagecopyresized`) forces exact `size × size` output dimensions, so a request for 1024 produces a 1024×1024 PNG regardless of endroid's block-size rounding. The resampler first peeks the PNG IHDR header and short-circuits without allocating any GD canvas when the source is already exact, which is essential at 4096px where each truecolor RGBA canvas is ~67 MB. Nearest-neighbor scaling is used deliberately to keep QR module edges crisp and hard — bilinear/anti-aliased scaling blurs module boundaries and can hurt scan reliability. Transparent backgrounds, custom colors, logo overlays, and non-square module styles all work at any allowed size.

**Memory and large sizes:** for `size >= 2048` the controller bumps `memory_limit` to `256M` via `ini_set` for that request. A 4096×4096 RGBA canvas is ~67 MB and the resize step can briefly hold both a source and destination canvas, so the default 128M limit is not enough. Any rendering failure is caught: the controller logs `[QR PNG Download] Failed for QR ID … at size …px: …` and the user gets a safe 403 ("Could not generate PNG at the requested size. Please try a smaller size or contact support.") rather than a raw 500. If a particular deployment cannot grant 256M, hosts can cap Team to 2048 by editing `PlanFeaturesSeeder` — the allow-list logic automatically drops 4096 from the size selector.

**Filename:** PNG downloads are named `f29-qr-{name}-{slug}-{size}px.png` so multiple-size downloads don't collide.

### Static QR generator

Static QR codes encode the payload **directly into the image**. Unlike the rest of the app's QR codes (which encode the managed `https://f29.us/{slug}` short URL), static QR codes are not redirects — once printed they are permanent and cannot be edited. f29 does not track their scans and does not save them anywhere.

**Routes** (all require authentication; POST routes require CSRF):

| Method | Path | Purpose |
| ------ | ---- | ------- |
| `GET`  | `/qr/static`               | Form page |
| `POST` | `/qr/static/preview`       | Validate + render in-page SVG preview |
| `POST` | `/qr/static/download/png`  | Render and download as PNG |
| `POST` | `/qr/static/download/svg`  | Render and download as SVG |

Static routes are registered **before** `/qr/{id}` in [public/index.php](public/index.php) so `/qr/static` resolves to the static controller, never as a dynamic-QR id. The public `/{slug}` catch-all remains last.

**No database writes.** Static generation never touches `qr_codes`, `short_links`, `scan_events`, `destination_history`, or `audit_logs`. There is no `static_qr_codes` table. The entire request lifecycle is: read POST → `StaticQrPayloadService::build()` → `QrCodeService::generateSvg/Png()` → return bytes. Nothing persists between requests, and static QR generation does not consume the user's `max_qr_codes` quota.

**Supported templates** (built by [`StaticQrPayloadService`](app/Services/StaticQrPayloadService.php)):

| Template     | Payload format |
| ------------ | -------------- |
| `text`       | Raw content (URL or plain text) |
| `wifi`       | `WIFI:T:WPA;S:Name;P:Pass;H:false;;` with `\ ; , :` backslash-escaped |
| `email`      | `mailto:user@example.com?subject=…&body=…` (rawurlencoded) |
| `vcard`      | vCard 3.0 with CRLF line endings, `\ ; , \n` escaped |

**Hard limits:** every built payload is rejected if it exceeds **1200 characters** (also enforced per-template). vCard rejection message is template-specific.

**Entitlement behavior:**

| Capability | Source feature key | Default for static QR |
| ---------- | ------------------ | --------------------- |
| Generation | (none — open to all logged-in users) | Allowed for Free |
| PNG download | `can_export_png` | Free: yes; size capped by `max_qr_download_size_px` |
| SVG download | `can_export_svg` | Free: no by default |
| Custom colors / transparent bg | `can_customize_qr_colors` | Free: no |
| Module style (gapped / circle) | `can_customize_qr_module_style` | Free: no |
| PNG size selector | `max_qr_download_size_px` | Free: 512 only |

Server-side rules for `buildStyleForUser`:
- **Unentitled users**: any submitted style field (colors, transparent, module style) is silently coerced back to the safe default. Hidden-form tampering can never produce styled output, and no error is surfaced — the user just sees the default render.
- **Entitled users**: submitted values are run through the same validators used by dynamic QR styling (`QrStyleService::normalizeHexColor`, `validateColors`, `validateModuleStyle`). If the submission fails any check (invalid hex, identical fg/bg, low contrast, unknown `module_style`, etc.) the controller re-renders the form with the error and **does not** render a preview or download.

PNG download size is additionally validated against the same `[512, 1024, 2048, 4096]` allow-list as dynamic QR.

**Filenames:** `f29-static-qr-{type}-{Ymd-His}-{size}px.png` and `f29-static-qr-{type}-{Ymd-His}.svg`. User-entered SSIDs / emails / names are **not** put in the filename.

**Progressive form UI.** The static QR form uses progressive enhancement JavaScript: without JavaScript all template sections remain visible; with JavaScript only the selected template section and plan-available style/logo controls are shown. Server-side validation and entitlement checks remain authoritative — the JS is purely presentation. The script lives in [`public/assets/js/app.js`](public/assets/js/app.js) (no inline `<script>`, no inline event handlers — CSP-safe).

**Static QR logo upload.** Entitled users (`can_upload_qr_logo = true`, i.e. Pro and Team by default) can upload a logo for a static QR code. The validation, allowed formats (PNG / JPG / WEBP), per-plan max size (`qr_logo_max_size_kb`), and coverage percent (`qr_logo_max_percent`) are reused from `QrStyleService::validateLogoUpload` so static and dynamic logos behave identically. Static logos force ECL to `H` exactly like dynamic logos.

The static-logo lifecycle is **stateless and session-scoped**, not database-persisted:

1. User uploads the logo on the preview form (`POST /qr/static/preview`).
2. `StaticQrLogoService::storeUploadedLogo` validates the file and copies it to `storage/static-qr-logos/static-{userId}-{randomHex}.{ext}` — outside the public web root and never with a user-controlled filename.
3. A 32-hex-char `static_logo_token` is stored in the PHP session for the uploading user, expiring 30 minutes after upload.
4. The preview renders with the logo; the page emits a hidden `<input name="static_logo_token">` that is forwarded into the PNG and SVG download forms.
5. On a download request, the controller looks up the logo by token (scoped to the same user), confirms the entitlement is still active, applies it to the style, and renders the image.
6. Expired tokens are cleaned on every static-QR request: session entries are removed and their files unlinked. The storage directory is also swept for orphan files older than 2×TTL.

Security details:
- The renderer (`QrCodeService::resolveLogoPath`) accepts either `$style['logo_path']` (a basename under `storage/qr-logos`, used by dynamic QR) or `$style['logo_absolute_path']` (a path supplied by the static service). For the absolute-path case it runs `realpath()` and rejects anything that doesn't resolve under `storage/qr-logos` or `storage/static-qr-logos` — there is no way for a user-supplied value to escape those two directories.
- An upload from a user without `can_upload_qr_logo` returns a 403 (`Your plan does not allow QR logo upload.`) rather than silently dropping the file.
- If the entitlement disappears between preview and download (e.g. downgrade), the cached session logo is ignored on the download path.
- Logos are never inserted into `qr_code_styles`, `audit_logs`, or any other table. No `static_qr_logos` table exists.

**What static QR does NOT do:**
- Not editable after download — the payload is baked into the image.
- No scan analytics, no pause / archive / restore.
- No domain moderation — static QR codes are not redirects controlled by f29.
- Logo files are not permanent — they expire after 30 minutes. Download promptly after preview.

### QR logo upload

Pro and Team users can upload a logo image that is composited into the center of their QR code. Logo upload is gated on `can_upload_qr_logo = true`.

**Supported formats:** PNG, JPEG, WEBP. SVG upload is not permitted (potential for script injection).

**Entitlement limits by plan** (values come from `PlanFeaturesSeeder` and apply to both dynamic and static QR logo upload, since `StaticQrLogoService` reuses `QrStyleService::validateLogoUpload`):

| Plan | `qr_logo_max_size_kb` | `qr_logo_max_percent` |
|------|:---------------------:|:----------------------:|
| Free / Starter | 0 (no logo upload) | 0 |
| Pro  | 512 KB | 25% |
| Team | 1024 KB | 30% |

**Storage:** Logo files are stored at `storage/qr-logos/` (outside the public web root) with generated filenames (`qr-{id}-{random_hex}.{ext}`). Original filenames are stored in `logo_original_filename` for display and audit purposes only. Files are never served directly from a public URL.

**Rendering:** `QrCodeService` reads the logo from the storage path and overlays it on the QR code. The logo is **aspect-ratio preserving**: the new private helper `QrCodeService::logoRenderDimensions($path, $maxBoxSize)` reads the source pixel dimensions and scales the logo to fit inside a `maxBoxSize × maxBoxSize` square, where `maxBoxSize = round(qrPixelSize * qr_logo_max_percent / 100)`. The logo is never upscaled beyond its native pixel dimensions. Both width and height are passed through to all three render paths (endroid square `logoResizeToWidth` + `logoResizeToHeight`, custom SVG `<image>`, custom PNG `imagecopyresampled`) so a 1000×500 logo on a 1024 px QR with 25 % renders at 256×128 centered, not stretched into 256×256. The same helper handles dynamic and static QR codes — they share `QrCodeService`. Both PNG and SVG downloads include the logo when enabled.

**Error correction:** Logo-enabled QR codes automatically use ECL=H (highest). When the logo is removed, ECL reverts to Q (if custom colors remain) or M (default).

**File lifecycle:** When a replacement logo is uploaded, the old file is deleted. When a logo is removed or the style is fully reset, the file is deleted.

**`QrStyleService` logo methods:**
```php
QrStyleService::saveLogo(int $qrId, array $file): array        // move file, upsert row, ECL=H
QrStyleService::removeLogo(int $qrId): void                    // clear logo fields, revert ECL
QrStyleService::validateLogoUpload(array $file, int $userId): array  // all validation checks
QrStyleService::logoStorageDir(): string                        // ensure dir + return path
QrStyleService::logoFilePath(string $filename): string          // full path without mkdir
QrStyleService::currentErrorCorrectionForStyle(array $style): string
```

**Audit events:** `qr_logo_uploaded` (with filename, MIME, size, plan limits, old logo present) and `qr_logo_removed` (with old filename, MIME, size). Filesystem paths are not logged.

### Admin-disabling a link

When an admin disables a short link:

- `status` is set to `'disabled'`
- `disabled_reason`, `disabled_by_user_id`, and `disabled_at` are recorded
- An optional internal `moderation_note` is stored (never shown publicly)
- The audit log records `admin_disabled` with slug, old status, reason, and whether a note was present
- Public redirects for the slug immediately show the unavailable page (generic message — reason not exposed)
- The link owner sees "This QR code has been disabled by an administrator" on their detail page

Restoring an admin-disabled link sets `status = 'active'` and logs `admin_restored`. Moderation metadata is preserved for audit context.

### Blocked domain enforcement

The `blocked_domains` table stores domains that users are not permitted to use as QR destinations. Blocking a domain also blocks all its subdomains:

- Blocking `example.com` prevents `https://example.com`, `https://www.example.com`, and `https://sub.example.com/path`
- Enforcement runs on QR creation, destination edit, and destination-history restore
- Failed attempts show "This destination domain is not allowed." — no internal reason is exposed
- Inactive blocked-domain rows do not enforce

External malware scanning (Google Safe Browsing, VirusTotal, etc.) is **not implemented**.

### Moderation admin pages

| Page | Path | Description |
|------|------|-------------|
| Moderated links | `/admin/moderation/links` | Filter links by status, owner, slug, or destination. Defaults to disabled links. |
| Link detail | `/admin/moderation/links/{id}` | Full context: owner, destination, moderation metadata, recent scan count. Disable or restore form. |
| Disable link | `POST /admin/moderation/links/{id}/disable` | Requires `disabled_reason`. Optional `moderation_note`. |
| Restore link | `POST /admin/moderation/links/{id}/restore` | Sets status to active. Preserves moderation metadata. |
| Blocked domains | `/admin/moderation/domains` | List all blocked domains with add form and active/inactive toggle. |
| Add domain | `POST /admin/moderation/domains` | Normalizes (lowercase, strips www.) before storage. Rejects duplicates. |
| Toggle domain | `POST /admin/moderation/domains/{id}/toggle` | Flips `is_active`. Inactive entries do not block destinations. |

All moderation POST endpoints are CSRF-protected and require the admin role. All actions are written to `audit_logs`.

---

## Legal and Policy Pages

The following public-facing policy pages are available at launch. All are **draft placeholders** — they have not been reviewed by legal counsel and should be reviewed and updated before wider public launch.

| Page | Path | Contents |
|------|------|----------|
| Terms of Service | `/terms` | Account responsibility, QR/link rules, moderation rights, liability limitation, billing note |
| Privacy Policy | `/privacy` | Data collected, scan analytics, IP hashing, cookies, no data sale, retention |
| Acceptable Use Policy | `/acceptable-use` | Prohibited uses (phishing, malware, spam, deception, illegal content), enforcement, no automated scanning notice |
| Report Abuse | `/abuse` | What to report, how to report, what to include, contact email |
| Contact | `/contact` | Support, abuse, and privacy contact emails; note that ticketing is not implemented |
| Help Center | `/help` | Plain-language explanation of dynamic vs static QR codes, styling, downloads, analytics, plans, account, billing, moderation, security, and an FAQ. Sidebar table of contents with anchored sections; no authentication, no form processing. |

All six pages are linked in the site footer. No authentication is required. No form processing or database writes occur on any of these pages. `help` is included in [`config/reserved_slugs.php`](config/reserved_slugs.php) so users cannot register `help` as a custom short slug.

### Policy email configuration

Three optional environment variables control the contact addresses shown on policy pages. If not set, the defaults shown are used.

| Variable | Default | Shown on |
|---|---|---|
| `SUPPORT_EMAIL` | `support@f29.us` | Contact page |
| `ABUSE_EMAIL` | `abuse@f29.us` | Abuse page, Contact page |
| `PRIVACY_EMAIL` | `privacy@f29.us` | Contact page |

These are display-only values used in policy pages and in outgoing notification emails when `MAIL_ENABLED=true`.

---

## Password Reset

### Flow

1. User visits `/forgot-password` and submits their email address.
2. The server looks up the account. If found, it creates a single-use token and sends a reset link to that address. If no account exists for that email the response is identical — no account enumeration.
3. The user clicks the link in the email (`/reset-password?token=…`).
4. The server validates the token and presents a new-password form.
5. On submission, the password is updated, the token is marked used, and a security notification is sent to the account email.

### Security properties

- **No enumeration** — `POST /forgot-password` always returns the same message.
- **Hashed tokens** — only `hash('sha256', $rawToken)` is stored; the raw token travels only in the email link.
- **60-minute expiry** — tokens expire one hour after creation.
- **Single-use** — tokens are marked `used_at` on first use; the `FOR UPDATE` row lock prevents concurrent replay.
- **Prior tokens invalidated** — when a new reset is requested, all earlier unused tokens for the same user are immediately voided.
- **Suspended accounts** — silently skipped during request; no email sent, no enumeration.
- **Session rotation** — if the browser performing the reset is currently logged in as the same user, `session_regenerate_id(true)` is called after the password change. Global session revocation (other sessions) is **not** implemented.

### Mail dependency

The reset email requires `MAIL_ENABLED=true` and valid SMTP configuration. If mail is disabled, the token is still written to the database (the reset link would work if the user could obtain the token another way, e.g. from the database directly during local dev), but no email is sent.

---

## Transactional Email

Transactional email is sent via **PHPMailer** (manually placed at `vendor/PHPMailer/`). No Composer package; no queue worker — emails are sent synchronously during the request and never block or crash the application on failure.

### Enable / disable

Set `MAIL_ENABLED=true` in `.env` and configure the SMTP variables. When `MAIL_ENABLED=false` (the default), all notification calls are silent no-ops.

### Notifications sent

| Event | Recipient(s) |
|---|---|
| Password reset requested | Account email (reset link, 60 min expiry) |
| Password reset completed | Account email (security alert) |
| Registration — email verification | New user (verification link, 24 h expiry) |
| Email change requested — verification | New address (confirm link, 24 h expiry) |
| Email change requested — security notice | Old address (notification only) |
| Email change completed | Old address (security alert) + new address (confirmation) |
| Subscription plan-change request submitted | User (confirmation) + optional admin address (`MAIL_ADMIN_ADDRESS`) |
| Plan-change request approved | User |
| Plan-change request denied | User |
| Plan-change request canceled by user | User |
| Plan-change request canceled by admin | User |
| Password changed | Account email (security alert) |
| Link disabled by admin | Link owner |
| Link restored by admin | Link owner |

### Architecture

- **`MailerService`** — low-level SMTP wrapper. Loads PHPMailer with `require_once`, configures SMTP from env, sends one message, catches all exceptions and logs to `storage/logs/error.log`. Never exposes SMTP credentials outside the service.
- **`NotificationService`** — high-level event methods. Each method loads the DB context it needs, builds the email, calls `MailerService::send()`, and catches any remaining exceptions. All methods no-op immediately when `MAIL_ENABLED=false`.
- Controllers call `NotificationService` after the audit log entry and before the flash message redirect. Failures are logged; the main application action always completes.

### Ops page

The `/admin/ops` page includes a **Mail Configuration** section showing whether mail is enabled, whether PHPMailer files are present, and the configured SMTP host, from-address, and admin notify address. The section label reads "configured" rather than "ready" to distinguish configuration presence from proven delivery.

A **Send Test Email** form (`POST /admin/ops/send-test-email`) sends a real email through the current SMTP configuration to confirm delivery before enabling payment flows. The form pre-fills the recipient with the current admin user's email. Failure is caught, logged to `storage/logs/error.log`, and reported via flash — no stack trace or SMTP credentials are exposed in the browser. Transactional email is synchronous (no queue).

---

## Billing State Model

> **Stripe integration plan:** See [`docs/STRIPE_PLAN.md`](docs/STRIPE_PLAN.md) for the complete
> Stripe architecture decisions, phased implementation order, required migrations, env vars,
> webhook events, and entitlement gating behavior.

### Schema

Two additions support the billing groundwork:

**`user_subscriptions` billing columns** (migration 018):

| Column | Type | Default | Purpose |
|--------|------|---------|---------|
| `billing_provider` | `VARCHAR(50)` | NULL | Payment provider slug (e.g. `stripe`) |
| `provider_customer_id` | `VARCHAR(255)` | NULL | Provider's customer object ID |
| `provider_subscription_id` | `VARCHAR(255)` | NULL | Provider's subscription object ID |
| `billing_status` | ENUM | `not_applicable` | Mirrors provider lifecycle state |
| `current_period_start` | `DATETIME` | NULL | Start of current billing period |
| `current_period_end` | `DATETIME` | NULL | End of current billing period |
| `trial_ends_at` | `DATETIME` | NULL | Trial expiry date |
| `cancel_at_period_end` | `TINYINT(1)` | 0 | Cancellation is scheduled for period end |

`billing_status` values:

| Value | Meaning |
|-------|---------|
| `not_applicable` | Default for manual/free plans — no billing provider involved |
| `manual` | Admin-assigned plan, manually tracked |
| `trialing` | Subscription is in a free trial period |
| `active` | Subscription is current and paid |
| `past_due` | Payment failed; grace period in effect |
| `canceled` | Subscription has been canceled |
| `unpaid` | Grace period exhausted; access should be gated |
| `incomplete` | Initial payment not yet confirmed |

**`plan_billing_prices` table** (migration 019): Maps local plans to payment provider price objects. `StripeService::createCheckoutSession()` (Phase 36) reads `provider_price_id` from active rows to create Checkout Sessions. One plan may have multiple mappings — e.g. Stripe monthly and yearly price IDs.

| Column | Type | Nullable | Purpose |
|--------|------|----------|---------|
| `plan_id` | `BIGINT UNSIGNED` | No | FK to `plans` (CASCADE DELETE) |
| `provider` | `VARCHAR(50)` | No | Provider slug (`stripe`) |
| `provider_mode` | ENUM(`'test'`/`'live'`) | No | Stripe mode for this mapping. Checkout only uses rows where `provider_mode` matches `STRIPE_MODE`. Stripe test and live Price IDs both start with `price_` — select the correct mode when creating mappings. |
| `provider_price_id` | `VARCHAR(255)` | No | The provider's price object ID (must start with `price_`) |
| `billing_cycle` | ENUM | No | `monthly` or `yearly` |
| `currency_code` | `CHAR(3)` | No | ISO 4217 (e.g. `USD`) |
| `amount_cents` | `INT UNSIGNED` | **Yes** | Amount in cents. Optional — may be left NULL while billing is not yet finalized |
| `is_active` | `TINYINT(1)` | No | Whether this mapping is live |

`amount_cents` is intentionally nullable. Price mappings can be created before exact billing amounts are confirmed, allowing the provider price ID to be recorded as groundwork before billing goes live. Stripe price IDs already carry their own amount; the local column is a convenience reference only.

### Admin visibility

Billing state fields (`billing_status`, `provider_subscription_id`, `current_period_end`, `cancel_at_period_end`) are visible in the user detail subscription history table and the global subscription history list (`/admin/subscriptions`). All columns are `—` for manual and free subscriptions where `billing_status = 'not_applicable'`.

The plan detail page (`/admin/plans/{id}`) shows all billing price mappings and provides add/activate/deactivate controls. A warning is shown when a paid plan (non-zero price) has no active price mapping.

### Stripe integration lifecycle

Checkout Session creation (Phase 36), webhook-based subscription activation (Phase 37), and full subscription lifecycle synchronization with billing-status gating (Phase 38) are implemented.

#### Checkout flow (Phase 36 + 37 implemented)

1. User clicks **Subscribe** on `/account/subscription` or `/pricing` — the form posts `plan_id` and `billing_cycle` to `POST /account/subscription/checkout`.
2. Server validates auth, verified email, plan eligibility, and active Stripe price mapping server-side (never trusts posted price ID).
3. `StripeService::getOrCreateCustomerForUser()` creates or retrieves the Stripe customer and persists `users.stripe_customer_id`.
4. `StripeService::createCheckoutSession()` creates a Stripe Checkout Session (`mode=subscription`) and inserts a local `stripe_checkout_sessions` row with `status='pending'`.
5. Server redirects user to the Stripe-hosted checkout URL.
6. User completes payment on Stripe.
7. Stripe fires `checkout.session.completed` → `POST /stripe/webhook` → `StripeWebhookService::handleCheckoutCompleted()` — cancels old active subscription, inserts new `user_subscriptions` row with `billing_provider='stripe'`, `billing_status='active'` (or `'trialing'`), `current_period_start/end`, clears entitlement cache.

> **Admin/manual assignment** (via `POST /admin/users/{id}/subscription`) remains available as a fallback for complimentary and grandfathered access. It is not the normal paid checkout path.

> **`POST /account/subscription/change`** (the previous request-review path) is blocked for paid plans when `STRIPE_ENABLED=true`, with an error directing the user to the Subscribe button.

#### Webhook events handled (Phase 37–38)

| Stripe event | Action |
|---|---|
| `checkout.session.completed` | Activate `user_subscriptions` row (Phase 37) |
| `checkout.session.expired` | Mark local checkout session expired (Phase 37) |
| `customer.subscription.updated` | Sync `billing_status`, `current_period_end`, `cancel_at_period_end`; set status=canceled if billing_status=canceled (Phase 38) |
| `customer.subscription.deleted` | Set `billing_status='canceled'`, `status='canceled'`, `canceled_at`; notify user (Phase 38) |
| `invoice.payment_succeeded` | Set `billing_status='active'`, update period dates (Phase 38) |
| `invoice.payment_failed` | Set `billing_status='past_due'`; notify user (Phase 38) |

#### Access gating (`EntitlementService`, Phase 38)

| `billing_status` | Entitlement behavior |
|---|---|
| `not_applicable`, `manual`, `active`, `trialing`, `past_due` | Subscribed plan features |
| `canceled` + `current_period_end` in the future | Subscribed plan features until period end |
| `canceled` + period end past/null | Free plan features |
| `unpaid`, `incomplete` | Free plan features immediately |
| No active `user_subscriptions` row | Free plan features |

`past_due` retains paid access during the grace window (no countdown timer in Phase 38).

#### Subscription cancel flow (Phase 38)

1. User clicks **Cancel Subscription** on `/account/subscription` (visible for active Stripe-backed subscriptions not already canceling).
2. Server POSTs to `POST /account/subscription/cancel-stripe` (CSRF + auth + verified email).
3. `StripeService::cancelSubscriptionAtPeriodEnd()` updates the Stripe subscription (`cancel_at_period_end: true`).
4. Local `user_subscriptions` row optimistically updated: `cancel_at_period_end=1`, `current_period_end` and `billing_status` synced from Stripe response.
5. Entitlement cache cleared; user retains paid access until `current_period_end`.
6. Webhook `customer.subscription.updated` confirms the update.
7. Webhook `customer.subscription.deleted` fires at period end → `billing_status='canceled'`, `status='canceled'`; user falls back to Free plan.

---

## QA Checklist

A manual regression checklist covering all features is maintained at:

```
docs/QA_CHECKLIST.md
```

Run through it before every production deployment.

---

## What Is NOT Implemented Yet

The following are intentionally absent:

- Stripe paid-to-paid plan switching (proration and upgrade/downgrade paths — deferred)
- Stripe Customer Portal integration (payment method updates, invoice history self-service)
- Invoice and payment-method self-service UI
- Tax, coupon, and discount logic
- Live-mode Stripe launch (test-mode QA checklist must pass first — see `docs/QA_CHECKLIST.md` section 17 and [`docs/STRIPE_LAUNCH_CHECKLIST.md`](docs/STRIPE_LAUNCH_CHECKLIST.md))
- Analytics retention data purge (retention is a query filter only — old rows are not deleted)
- Geolocation in scan events (country/region/city stored as NULL)
- Global session revocation on password reset (only the current browser session is rotated; other active sessions remain valid)
- Multi-factor authentication (MFA / TOTP)
- Team / workspace / multi-user account features
- API endpoints (REST or otherwise)
- External malware / phishing scanning (Google Safe Browsing, VirusTotal, etc.)
