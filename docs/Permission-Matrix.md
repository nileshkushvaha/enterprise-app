# Permission Matrix

## Roles

| Role | Description | How granted |
|---|---|---|
| `super_admin` | Unconditional access to everything via `Gate::before()` | Assigned by `SuperAdminSeeder` (user ID 1) |
| `student` | Default frontend role | Assigned on registration via `RegistrationSettings::default_role` |
| Custom roles | Admin-configurable | Created in admin under Roles resource |

Super admin bypass is implemented in `AppServiceProvider::registerSuperAdminGate()`. It also falls back to checking `role id = 1` for safety. **New permissions are automatically assigned to super_admin** via the `Permission::created` observer in the same provider.

---

## Gate Abilities (Named Gates)

All defined in `AppServiceProvider::registerPolicies()`.

### Profile

| Ability | Policy method | Who can |
|---|---|---|
| `profile.view` | `ProfilePolicy::view` | Owner |
| `profile.update` | `ProfilePolicy::update` | Owner |
| `password.change` | `ProfilePolicy::changePassword` | Owner |

### Security

| Ability | Policy method |
|---|---|
| `security.authentication.view` | `SecurityPolicy::viewAuthentication` |
| `security.authentication.update` | `SecurityPolicy::updateAuthentication` |
| `security.password_policy.view` | `SecurityPolicy::viewPasswordPolicy` |
| `security.password_policy.update` | `SecurityPolicy::updatePasswordPolicy` |
| `security.login_security.view` | `SecurityPolicy::viewLoginSecurity` |
| `security.login_security.update` | `SecurityPolicy::updateLoginSecurity` |
| `security.session.view` | `SecurityPolicy::viewSession` |
| `security.session.update` | `SecurityPolicy::updateSession` |
| `security.registration.view` | `SecurityPolicy::viewRegistration` |
| `security.registration.update` | `SecurityPolicy::updateRegistration` |
| `security.account_protection.view` | `SecurityPolicy::viewAccountProtection` |
| `security.account_protection.update` | `SecurityPolicy::updateAccountProtection` |

### System Tools

| Ability | Policy method |
|---|---|
| `cache_manager.view` | `CacheManagerPolicy::viewPage` |
| `cache_manager.clear` | `CacheManagerPolicy::clearApplicationCache` |
| `cache_manager.optimize` | `CacheManagerPolicy::optimize` |
| `scheduler_monitor.view` | `SchedulerMonitorPolicy::viewPage` |
| `scheduler_monitor.run` | `SchedulerMonitorPolicy::runTask` |
| `queue_monitor.view` | `QueueMonitorPolicy::viewPage` |

---

## Model Policies (Gate::policy)

| Model | Policy class | Registered in |
|---|---|---|
| `User` | `ProfilePolicy` | `AppServiceProvider` |
| `NavigationMenu` | `NavigationMenuPolicy` | `AppServiceProvider` |
| `Activity` (activitylog) | `ActivityLogPolicy` | `AppServiceProvider` |
| `ContentBlock` | `ContentBlockPolicy` | `CmsServiceProvider` |

---

## Shield-Generated Permissions

Filament Shield generates permissions in the format `{action}_{resource}` for each Filament Resource. These are stored in the `permissions` table and assignable to roles.

Resources with Shield permissions:
- `ActivityLog` — view_activity_log, view_any_activity_log
- `Countries` — view, view_any, create, update, delete, delete_any
- `Navigation` — (menus and items)
- `PageBlocks` — view, view_any, create, update, delete
- `Pages` — view, view_any, create, update, delete, replicate
- `Permissions` — view, view_any, create, update, delete
- `PostCategories` — view, view_any, create, update, delete
- `Posts` — view, view_any, create, update, delete, replicate
- `Roles` — view, view_any, create, update, delete
- `Tags` — view, view_any, create, update, delete
- `Users` — view, view_any, create, update, delete, restore

After adding a new Resource, regenerate permissions:

```bash
php artisan shield:generate --resource=MyNewResource
```

---

## How Access Control Layers Work

For a Security settings page, there are three layers:

```
Layer 1: FilamentUser::canAccessPanel()    — must be active + verified (or super_admin)
Layer 2: HasSecurityAccess::canAccess()    — checks security.{page}.view permission
                                             (hides page from sidebar if denied)
Layer 3: Gate::authorize() in save()       — checks security.{page}.update permission
                                             (blocks write even if page is visible)
```

This means a user can have view-only access to a Security page (read settings, cannot save).

For Filament Resources, the standard Filament policy methods (`canViewAny`, `canCreate`, etc.) map to Shield permissions. See `ContentBlockPolicy` for the pattern.

---

## ContentBlock Permission Model

`ContentBlockPolicy` (`app/Policies/ContentBlockPolicy.php`) uses a polymorphic ownership model — permissions depend on whether the block belongs to a Page or a Post:

- Users with `update_page` permission can update/delete Page blocks
- Users with `update_post` permission can update/delete Post blocks
- `viewAny` and `create` require either `update_page` OR `update_post`

The `ContentBlockPolicyTest` covers 7 scenarios including cross-ownership denial.

---

## Adding a New Gate Ability

1. Define in `AppServiceProvider::registerPolicies()`:
   ```php
   Gate::define('module.resource.action', [ModulePolicy::class, 'methodName']);
   ```
2. Add the policy method to the policy class
3. Create the permission record (if role-assignable) via a seeder or settings migration
4. Call `Gate::authorize('module.resource.action')` in the relevant controller/page method
