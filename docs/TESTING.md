# Testing Guide

## Overview

```
Development  →  .env              →  enterprise_app
Testing      →  .env.testing      →  enterprise_app_testing
```

Tests use `RefreshDatabase`, which runs `migrate:fresh` before each test class. That wipes all tables. **`enterprise_app_testing` is disposable. `enterprise_app` is not.**

---

## Running tests

```bash
composer test
```

This maps to `php artisan test --env=testing`. The `--env=testing` flag tells artisan to boot with `APP_ENV=testing`, which causes Laravel to load `.env.testing` before populating the process environment.

Use `composer test` as the standard project command. If you run `php artisan test` directly, always include `--env=testing`. The safety guard in `TestCase` will catch a misconfigured run and abort with a clear message before `RefreshDatabase` can do any damage.

Running a subset:

```bash
# Single test class
php artisan test --env=testing --filter CacheManagerServiceTest

# Single method
php artisan test --env=testing --filter "CacheManagerServiceTest::test_clearApplicationCache_returns_result_array"

# Single file
php artisan test --env=testing tests/Feature/ActivityLogResourceTest.php
```

---

## First-time setup

Create the dedicated test database once:

```bash
mysql -uroot -p -e "CREATE DATABASE IF NOT EXISTS enterprise_app_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Migrations run automatically via `RefreshDatabase` on the first test run — no manual step needed.

---

## Why `RefreshDatabase` is safe here

`RefreshDatabase` uses database transactions on databases that support them (rollback after each test, very fast). On MySQL it falls back to `migrate:fresh` (drop all tables, re-run migrations) before each test class.

Because `enterprise_app_testing` exists only for tests and contains no real data, `migrate:fresh` is safe. The production database `enterprise_app` is never touched as long as tests run via `composer test`.

---

## The safety guard

`tests/TestCase.php` runs two checks before every test:

```php
// 1. Environment must be "testing"
if (! app()->environment('testing')) {
    $this->fail('SAFETY ABORT: APP_ENV is [' . app()->environment() . '], not [testing]...');
}

// 2. Database must not be the development database
$database = config('database.connections.' . config('database.default') . '.database');
if ($database === 'enterprise_app') {
    $this->fail('SAFETY ABORT: tests are pointed at the development database...');
}
```

The double check covers two distinct failure modes:
- **Check 1** catches running without `--env=testing` (APP_ENV stays `local`, `.env.testing` never loads).
- **Check 2** catches a misconfigured `.env.testing` where APP_ENV is correct but DB_DATABASE still points at the dev database.

The database check uses the hardcoded name `enterprise_app` (not `enterprise_app_testing`) so it stays valid for any CI or Docker database name — only the development database is explicitly blocked.

---

## CI / CD and Docker

Ensure `APP_ENV=testing` is set before artisan boots, then run:

```bash
php artisan test --env=testing
```

GitHub Actions example:

```yaml
- name: Run tests
  env:
    APP_ENV: testing
    DB_DATABASE: enterprise_app_testing
    DB_USERNAME: root
    DB_PASSWORD: ""
  run: php artisan test --env=testing
```

The safety guard checks `APP_ENV=testing` and blocks `DB_DATABASE=enterprise_app`. Any other database name (including a CI-specific one) will pass.

---

## Verifying environment configuration

Before running tests — or when onboarding — run the doctor command:

```bash
php artisan app:doctor                    # development
php artisan app:doctor --env=testing      # testing
```

Expected output for development:

```
Environment Check

✓  Environment          local
✓  Connection           mysql
✓  Database             enterprise_app
✓  Cache                database
✓  Queue                database
✓  Mail                 smtp
✓  Session              database

All checks passed.
```

Expected output for testing:

```
Environment Check

✓  Environment          testing
✓  Connection           mysql
✓  Database             enterprise_app_testing
✓  Cache                array
✓  Queue                sync
✓  Mail                 array
✓  Session              array

All checks passed.
```

---

## Common pitfalls

| Symptom | Cause | Fix |
|---------|-------|-----|
| `SAFETY ABORT: APP_ENV is [local]` | Ran `php artisan test` without `--env=testing` | Use `composer test` |
| Tests pass but wipe production data | Guard was removed or bypassed | Restore `guardAgainstProductionDatabase()` in `TestCase` |
| `.env` has two `DB_DATABASE` lines | Stale duplicate — dotenv takes the first value | Remove the duplicate |
| `migrate:fresh` fails on missing table | Migration added but not yet run | `composer test` runs migrations automatically |
| `Undefined variable $errors` in blade | Component rendered outside a request context | Guard with `isset($errors)` before accessing |
