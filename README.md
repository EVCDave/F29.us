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
| GET | `/login` | Login form |
| POST | `/login` | Login submit |
| GET | `/register` | Register form |
| POST | `/register` | Register submit |
| POST | `/logout` | Logout |
| GET | `/dashboard` | User dashboard |
| GET | `/qr` | QR code list |
| GET | `/qr/create` | Create QR form |
| POST | `/qr` | Create QR submit |
| GET | `/qr/{id}` | QR detail |
| GET | `/qr/{id}/edit` | Edit destination form |
| POST | `/qr/{id}/update` | Save destination |
| POST | `/qr/{id}/pause` | Pause short link |
| POST | `/qr/{id}/resume` | Resume short link |
| GET | `/qr/{id}/download/png` | Download QR as PNG |
| GET | `/qr/{id}/download/svg` | Download QR as SVG |
| GET | `/qr/{id}/analytics` | QR analytics page |
| GET | `/{slug}` | **Public redirect** (catch-all, last priority) |

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
| Admin home | `/admin` | Overview stats (user count, QR count) |
| User list | `/admin/users` | Searchable list of all users (capped at 100), with role, status, and active plan |
| User detail | `/admin/users/{id}` | Full user info, subscription history, effective entitlements with source (plan vs override), and active overrides |
| Change subscription | `POST /admin/users/{id}/subscription` | Manually assign a plan, billing cycle, and optional grandfathered flag. Cancels the current active subscription and creates a new one. |
| Add / update override | `POST /admin/users/{id}/overrides` | Set a per-user feature override (int, bool, or string) with optional expiry and note. Upserts on `(user_id, feature_key)`. |
| Delete override | `POST /admin/users/{id}/overrides/{id}/delete` | Remove a specific override for a user. |

All admin POST endpoints are CSRF-protected and require the admin role. Non-admins receive a 403. All subscription changes and override operations are written to `audit_logs`.

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
| Edit destination URL | ✓ |
| Pause / Resume short link | ✓ |
| Download PNG (requires GD extension) | ✓ |
| Download SVG | ✓ |
| Audit logging (create, edit, pause, resume) | ✓ |
| **Public slug redirect (`/{slug}` → destination, HTTP 302)** | ✓ |
| **Unavailable page for paused/disabled links** | ✓ |
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

## What Is NOT Implemented Yet

The following are intentionally absent:

- Content-Security-Policy (requires inline style refactoring first)
- Analytics retention data purge (retention is a query filter only)
- Geolocation in scan events (country/region/city stored as NULL)
- Billing / payment integration
- Public subscription checkout
- Team features
- API endpoints
