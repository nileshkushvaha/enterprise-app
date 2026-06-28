# Testing

## The Rule

```bash
composer test        # always use this
```

This runs `php artisan test --env=testing`. Never run `php artisan test` bare — it uses `.env` (dev database).

---

## Environment Separation

```
.env          → APP_ENV=local  → DB: enterprise_app       (development — never touch in tests)
.env.testing  → APP_ENV=testing → DB: enterprise_app_testing  (disposable)
```

`RefreshDatabase` runs `migrate:fresh` which wipes all tables. It's safe on `enterprise_app_testing`. It would destroy the dev database. The safety guard in `TestCase` catches misconfigured runs before any damage occurs.

---

## Safety Guard

`tests/TestCase.php` aborts with a clear message if:
1. `APP_ENV` is not `testing` (caught by Check 1)
2. The active database is `enterprise_app` (caught by Check 2)

**Never remove or weaken this guard.** It exists because of a prior incident where `migrate:fresh` wiped the development database.

---

## First-Time Setup

```bash
mysql -uroot -p -e "CREATE DATABASE IF NOT EXISTS enterprise_app_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

No manual migration step — `RefreshDatabase` runs migrations automatically.

---

## Running Tests

```bash
# Full suite (762 tests)
composer test

# Single class
php artisan test --env=testing --filter SecurityPolicyTest

# Single method
php artisan test --env=testing --filter "AuthenticationSettingsTest::test_save_logs_changed_fields_diff"

# Single file
php artisan test --env=testing tests/Feature/Security/AuthenticationSettingsTest.php

# All Security tests
php artisan test --env=testing tests/Feature/Security/
```

---

## Test Structure

```
tests/
├── TestCase.php                           — base class with safety guard + RefreshDatabase
├── Unit/
│   ├── Navigation/                        — unit tests for navigation system
│   └── Services/                          — unit tests for Block services, SeoManager
└── Feature/
    ├── Security/                          — 8 test classes, one per Security settings page
    │   ├── AuthenticationSettingsTest.php
    │   ├── PasswordPolicySettingsTest.php
    │   ├── LoginSecuritySettingsTest.php
    │   ├── SessionSettingsTest.php
    │   ├── RegistrationSettingsTest.php
    │   ├── AccountProtectionSettingsTest.php
    │   ├── PasswordRuleBuilderTest.php
    │   └── SecurityPolicyTest.php
    ├── Navigation/                        — 13 test classes for navigation system
    ├── Frontend/                          — frontend rendering integration tests
    └── *.php                              — feature tests (CMS, cache, scheduler, etc.)
```

---

## Settings Tests

Settings tests have a required `setUp` call:

```php
protected function setUp(): void
{
    parent::setUp();

    // Must use path-based migrate — 'settings:migrate' command does not exist
    $this->artisan('migrate', ['--path' => 'database/settings']);

    // Seed roles and permissions for this test class only
    $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'security.authentication.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'security.authentication.update', 'guard_name' => 'web']);

    $this->superAdmin = User::factory()->create(['status' => 'active']);
    $this->superAdmin->assignRole($superAdminRole);
}
```

After saving settings in a test, use `refresh()` to avoid stale in-memory state:

```php
$settings = app()->make(PasswordPolicySettings::class)->refresh();
$this->assertSame(12, $settings->min_length);
```

---

## Filament Page Tests (Livewire)

Test Filament pages via `Livewire::test()`:

```php
$this->actingAs($this->superAdmin);

Livewire::test(PasswordPolicyPage::class)
    ->set('data.min_length', 12)
    ->set('data.expiry_enabled', true)  // set toggle BEFORE dependent field
    ->set('data.expiry_days', 60)
    ->call('save')
    ->assertNotified('Password policy saved');
```

**Critical:** Fields with `.visible(fn($get) => ...)` are excluded from `getState()` when hidden. Always set the controlling toggle before the dependent field, or the dependent field's value will be ignored on save.

---

## PermissionDoesNotExist in Tests

When a test only seeds one page's permissions, Filament builds all navigation items and calls `canAccess()` on all Security pages. If a permission doesn't exist in the test DB, `hasPermissionTo()` throws `PermissionDoesNotExist`.

This is handled in `HasSecurityAccess::canAccess()` with a try/catch. Do not remove it — it exists specifically to prevent this cascade during tests.

---

## Diagnostic Command

```bash
php artisan app:doctor                    # verify dev environment
php artisan app:doctor --env=testing      # verify test environment
```

Expected test output:
```
✓  Environment    testing
✓  Database       enterprise_app_testing
✓  Cache          array
✓  Queue          sync
✓  Mail           array
✓  Session        array
```

---

## CI Setup

```yaml
- name: Run tests
  env:
    APP_ENV: testing
    DB_DATABASE: enterprise_app_testing
    DB_USERNAME: root
    DB_PASSWORD: ""
  run: php artisan test --env=testing
```

The safety guard passes any `DB_DATABASE` other than `enterprise_app` — CI-specific database names work without changes.

---

## Common Pitfalls

| Symptom | Cause | Fix |
|---|---|---|
| `SAFETY ABORT: APP_ENV is [local]` | Ran `php artisan test` without `--env=testing` | Use `composer test` |
| Test saves setting but assertion reads stale value | In-memory settings cache not flushed | Use `app()->make(Settings::class)->refresh()` |
| Field value ignored in Livewire test | Field is hidden (`.visible()`) when value is set | Set the controlling toggle first |
| `The command does not exist` for settings:migrate | Wrong command | Use `$this->artisan('migrate', ['--path' => 'database/settings'])` |
| `PermissionDoesNotExist` during canAccess() | Permission not seeded in test setUp | Expected — caught by HasSecurityAccess. Check setUp is creating the right permissions. |
| `migrate:fresh` fails on missing table | Migration added but not run on test DB | Run `composer test` — RefreshDatabase handles it |
