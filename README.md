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

### 2. Create the database

```sql
CREATE DATABASE f29us_qr
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 3. Run migrations

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

### 4. Run seeders

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

### 5. Serve locally

```bash
php -S localhost:8000 -t public
```

Open `http://localhost:8000` in your browser.

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

| Method | Path | Description |
|--------|------|-------------|
| GET | `/` | Homepage |
| GET | `/pricing` | Public pricing page — shows public active plans with features and selection |
| GET | `/login` | Login form |
| POST | `/login` | Login submit |
| GET | `/register` | Register form |
| POST | `/register` | Register submit |
| POST | `/logout` | Logout |
| GET | `/dashboard` | User dashboard |
| GET | `/account/subscription` | Current plan, lifecycle status, usage summary, pending/resolved request history, plan comparison |
| POST | `/account/subscription/change` | Self-service plan change (free = immediate; paid = creates request) |
| POST | `/account/subscription/request-cancel` | Cancel a pending subscription change request |
| GET | `/qr` | QR code list |
| GET | `/qr/create` | Create QR form |
| POST | `/qr` | Create QR submit |
| GET | `/qr/{id}` | QR detail — info table, preview, copy URL, action buttons |
| GET | `/qr/{id}/edit` | Edit QR code (name always; destination if plan allows) |
| POST | `/qr/{id}/update` | Save name and/or destination |
| POST | `/qr/{id}/pause` | Pause short link |
| POST | `/qr/{id}/resume` | Resume short link |
| POST | `/qr/{id}/archive` | Archive short link (stops redirecting) |
| POST | `/qr/{id}/restore` | Restore an archived link to active |
| POST | `/qr/{id}/destination-history/{historyId}/restore` | Restore a previous destination URL from history |
| GET | `/qr/{id}/download/png` | Download QR as PNG |
| GET | `/qr/{id}/download/svg` | Download QR as SVG |
| GET | `/qr/{id}/analytics` | QR analytics page (date range filter, bot toggle, summary cards) |
| GET | `/qr/{id}/analytics/export` | Export analytics as CSV (requires `can_export_analytics` entitlement) |
| GET | `/{slug}` | **Public redirect** (catch-all, last priority) |
| GET | `/admin/plans` | Plan catalog list |
| GET | `/admin/plans/create` | Create plan form |
| POST | `/admin/plans` | Create plan submit |
| GET | `/admin/plans/{id}` | Plan detail (features, subscription counts) |
| GET | `/admin/plans/{id}/edit` | Edit plan metadata |
| POST | `/admin/plans/{id}/update` | Save plan metadata |
| POST | `/admin/plans/{id}/features` | Add feature |
| POST | `/admin/plans/{id}/features/{featureId}/update` | Update feature value |
| POST | `/admin/plans/{id}/features/{featureId}/delete` | Delete feature |
| POST | `/admin/plans/{id}/billing-prices` | Add billing price mapping (provider, price ID, cycle) |
| POST | `/admin/plans/{id}/billing-prices/{priceId}/toggle` | Activate or deactivate a billing price mapping |

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
Aggregates scan event data for the analytics page. All queries are scoped to a `short_link_id` and a retention window in days.

```php
AnalyticsService::getTotalScans($shortLinkId, $retentionDays);     // int (bots excluded)
AnalyticsService::getBotCount($shortLinkId, $retentionDays);        // int
AnalyticsService::getDailyCounts($shortLinkId, $retentionDays);     // [['scan_date', 'total'], ...]
AnalyticsService::getDeviceBreakdown($shortLinkId, $retentionDays); // [['device_type', 'total'], ...]
AnalyticsService::getTopReferers($shortLinkId, $retentionDays);     // top 10 referers
```

**Retention note:** `analytics_retention_days` is currently a query visibility rule, not a data purge. Older rows remain in `scan_events`; they simply fall outside the `DATE_SUB(NOW(), INTERVAL ? DAY)` filter.

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
```
CSP is not yet applied — the current inline-style-heavy layout requires style refactoring before a useful policy can be written.

### Download error handling
QR library failures (e.g. `composer install` not yet run) are caught, logged server-side, and return a safe 403 message rather than leaking stack traces.

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
| Operations | `/admin/ops` | System health snapshot: environment, PHP version, extensions (GD, mbstring), filesystem checks, migration count, database counters, login activity (24 h). |

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

Billing, public checkout, and payment processor integration are **not implemented**. Plan assignment is manual via the admin area only.

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
| **Security response headers (X-Frame-Options, nosniff, Referrer-Policy)** | ✓ |
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

## Subscription Groundwork

### Public pricing page

`/pricing` is publicly accessible. It shows all plans with `is_public = 1`, `is_active = 1`, `is_legacy = 0`, ordered by `sort_order`. Authenticated users see which plan they are currently on and which plans have pending requests.

### Account subscription page

`/account/subscription` is accessible to authenticated users. It shows:

- **Current plan** — plan name, billing cycle, started date, grandfathered date if set, legacy badge if applicable, and a contextual status message
- **Usage summary** — QR code count vs. limit, analytics retention days, custom slug and SVG export availability (drawn from live entitlements)
- **Pending plan-change requests** — any waiting requests with cancel option; shows "none" state when empty
- **Recent request history** — last 10 resolved (approved/denied/canceled) requests with outcome messaging
- **Available plans** — feature comparison table with clear action labels: `Current Plan`, `Switch to Free`, `Request Review`, `Request Pending`

### Plan selection behavior

Switching to a plan is handled server-side:

- **Free plan** (`internal_name = 'free_v1'`): the switch is immediate. The current subscription is closed and a new active free subscription is created.
- **All other public plans**: a `subscription_change_requests` row is created with `status = 'pending'` for admin review. No subscription change occurs until an admin approves it.

All plan eligibility is validated server-side (public, active, not legacy). The form `plan_id` value is never trusted without a database lookup. Billing is not yet automated — no charges apply.

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

All five pages are linked in the site footer. No authentication is required. No form processing or database writes occur on any of these pages.

### Policy email configuration

Three optional environment variables control the contact addresses shown on policy pages. If not set, the defaults shown are used.

| Variable | Default | Shown on |
|---|---|---|
| `SUPPORT_EMAIL` | `support@f29.us` | Contact page |
| `ABUSE_EMAIL` | `abuse@f29.us` | Abuse page, Contact page |
| `PRIVACY_EMAIL` | `privacy@f29.us` | Contact page |

No email sending is implemented. These are display-only values.

---

## Billing State Model

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

**`plan_billing_prices` table** (migration 019): Maps local plans to payment provider price objects for future billing integration. This is groundwork only — no payment is processed from these mappings. One plan may have multiple mappings — e.g. Stripe monthly and yearly price IDs.

| Column | Type | Nullable | Purpose |
|--------|------|----------|---------|
| `plan_id` | `BIGINT UNSIGNED` | No | FK to `plans` (CASCADE DELETE) |
| `provider` | `VARCHAR(50)` | No | Provider slug (e.g. `stripe`) |
| `provider_price_id` | `VARCHAR(255)` | No | The provider's price object ID |
| `billing_cycle` | ENUM | No | `monthly` or `yearly` |
| `currency_code` | `CHAR(3)` | No | ISO 4217 (e.g. `USD`) |
| `amount_cents` | `INT UNSIGNED` | **Yes** | Amount in cents. Optional — may be left NULL while billing is not yet finalized |
| `is_active` | `TINYINT(1)` | No | Whether this mapping is live |

`amount_cents` is intentionally nullable. Price mappings can be created before exact billing amounts are confirmed, allowing the provider price ID to be recorded as groundwork before billing goes live. Stripe price IDs already carry their own amount; the local column is a convenience reference only.

### Admin visibility

Billing state fields (`billing_status`, `provider_subscription_id`, `current_period_end`, `cancel_at_period_end`) are visible in the user detail subscription history table and the global subscription history list (`/admin/subscriptions`). All columns are `—` for manual and free subscriptions where `billing_status = 'not_applicable'`.

The plan detail page (`/admin/plans/{id}`) shows all billing price mappings and provides add/activate/deactivate controls. A warning is shown when a paid plan (non-zero price) has no active price mapping.

### Future Stripe integration lifecycle

The following is a reference for wiring up a payment provider. Nothing here is implemented — it describes the intended integration contract.

#### Checkout flow

1. User selects a paid plan on `/account/subscription` and submits the change form.
2. Server creates a `subscription_change_requests` row (existing behavior).
3. Admin approves the request (existing behavior).
4. **Future:** On approval, instead of immediately creating a subscription, the server creates a Stripe Checkout Session using the `provider_price_id` from `plan_billing_prices` for the selected plan and cycle.
5. User completes payment in Stripe Checkout.
6. Stripe fires `checkout.session.completed` → webhook handler creates the `user_subscriptions` row and sets `billing_provider='stripe'`, `provider_customer_id`, `provider_subscription_id`, `billing_status='active'`, `current_period_start/end`.

#### Webhook events to handle

| Stripe event | Action |
|---|---|
| `customer.subscription.created` | Set `billing_status='active'`, populate period dates |
| `customer.subscription.updated` | Sync `billing_status`, `current_period_end`, `cancel_at_period_end` |
| `customer.subscription.deleted` | Set `billing_status='canceled'`, set `canceled_at` |
| `invoice.payment_failed` | Set `billing_status='past_due'` |
| `invoice.payment_succeeded` | Reset `billing_status='active'`, update period dates |
| `customer.subscription.trial_will_end` | Email notice (3 days before) |

All webhook payloads must be verified with `Stripe-Signature` using the endpoint's signing secret. Use `stripe.webhooks.constructEvent()` from the official Stripe PHP SDK.

#### Access gating

When billing is live, `EntitlementService` should additionally check `billing_status`:
- `active`, `trialing`, `manual` → full access
- `past_due` → restricted access (show payment-failed banner, soft-block new QR creation)
- `unpaid`, `canceled` → treat as free tier
- `incomplete` → block non-free features; prompt to complete payment

This gating logic should be implemented in `EntitlementService` so all access checks remain in one place.

#### Subscription cancel flow

1. User requests cancellation from `/account/subscription`.
2. Server calls `stripe.subscriptions.update(subId, { cancel_at_period_end: true })`.
3. Set `cancel_at_period_end = 1` locally.
4. On `customer.subscription.deleted` webhook → set `billing_status='canceled'`.
5. User retains access until `current_period_end`.

---

## What Is NOT Implemented Yet

The following are intentionally absent:

- Content-Security-Policy (requires inline style refactoring first)
- Analytics retention data purge (retention is a query filter only)
- Geolocation in scan events (country/region/city stored as NULL)
- Payment processing and checkout (Stripe integration — schema groundwork is in place; see Billing State Model)
- Automated billing webhooks and access gating based on billing state
- Team features
- API endpoints
- External malware / phishing scanning (Google Safe Browsing, VirusTotal, etc.)
