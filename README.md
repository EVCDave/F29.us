# f29.us Dynamic QR

Dynamic QR codes that you can redirect to any URL at any time — no reprinting required.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.2+ |
| MariaDB or MySQL | MariaDB 10.6+ / MySQL 8.0+ |
| Web server | Apache with `mod_rewrite`, or PHP built-in server for local dev |

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
APP_URL=http://localhost:8000
APP_DEBUG=true

# Required: HMAC secret for IP hashing in login throttle
# Generate: php -r "echo bin2hex(random_bytes(32));"
APP_KEY=your_generated_key_here

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
  RUN     009_create_audit_logs_table ... done

Migrations: 9 run, 0 skipped.
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

## Directory Structure

```
/app
  /Controllers        Route handlers (one class per controller)
  /Models             Data models  [placeholder — not yet built]
  /Services           Business logic  [placeholder — not yet built]
  /Views              PHP view templates
    /auth             Login and register pages
    /errors           Error pages (404, etc.)
    /layouts          Shared layout wrappers
    /qr               QR code pages
  /Middleware         Request middleware  [placeholder — not yet built]

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
  /logs               PHP error log (git-ignored)

bootstrap.php         Loads .env, config, DB connection, core classes
migrate.php           CLI: run pending migrations
seed.php              CLI: run seeders
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
LoginThrottleService::hashIp($remoteAddr);           // ?string SHA-256 or null
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
| **Login throttle (5 attempts → 15 min lockout, session-based)** | ✓ |
| **Input length limits (name 200, URL 2048)** | ✓ |
| **URL header-injection defense** | ✓ |
| **Security response headers (X-Frame-Options, nosniff, Referrer-Policy)** | ✓ |
| **Download failure safe error page** | ✓ |

## What Is NOT Implemented Yet

The following are intentionally absent:

- Content-Security-Policy (requires inline style refactoring first)
- Analytics retention data purge (retention is a query filter only)
- Geolocation in scan events (country/region/city stored as NULL)
- Billing / payment integration
- Team features
- Admin panel
- API endpoints
