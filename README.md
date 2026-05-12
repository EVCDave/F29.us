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

## What Is NOT Implemented Yet

This is the **scaffold phase only**. The following are intentionally absent:

- Login / logout / session management
- Registration with password hashing
- CSRF protection
- Authentication middleware and access control
- Entitlement checks (plan limits)
- QR code generation
- Short link redirect logic
- Analytics data collection and display
- Custom slug generation and validation
- Billing / payment integration
- Team features
- Admin panel
- API endpoints
