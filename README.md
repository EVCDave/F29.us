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

## Routes (Scaffold)

| Method | Path | Status |
|--------|------|--------|
| GET | `/` | Placeholder homepage |
| GET | `/dashboard` | Placeholder dashboard |
| GET | `/login` | Placeholder login form |
| GET | `/register` | Placeholder register form |
| GET | `/qr` | Placeholder QR list |
| GET | `/qr/create` | Placeholder QR create form |

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

## What Is NOT Implemented Yet

The following are intentionally absent:

- CSRF protection on forms (deferred)
- Public redirect by slug (`/{slug}` → destination)
- Short link redirect logic
- Analytics data collection and display
- Custom slug generation and validation
- Billing / payment integration
- Team features
- Admin panel
- API endpoints
