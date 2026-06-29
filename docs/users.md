# Users

## Model

`App\Models\User` — standard Laravel authenticatable, implements `FilamentUser`, `MustVerifyEmail`.

Traits: `HasFactory`, `HasRoles` (Spatie), `LogsActivity` (Spatie), `Notifiable`.

Key fields: `name`, `first_name`, `last_name`, `email`, `status`, `email_verified_at`, `locked_until`, `totp_secret`, `totp_recovery_codes`, `password_changed_at`, `force_password_change`.

Status values: `active`, `inactive`, `pending`, `blocked`. Constant: `User::STATUS_ACTIVE`.

## Filament Resource

`app/Filament/Resources/Users/` — Administration group, sort 1.

Pages: `ListUsers`, `CreateUser`, `EditUser`.

`CreateUser` and `EditUser` log to `activity('users')` after save.

Role assignment in `EditUser` triggers `roles_updated` activity event and sends admin bell notification.

## Account approval workflow

When `RegistrationSettings::require_approval` is true, new registrations land in `pending` status. Admin changes status to `active` via `EditUser`, which dispatches `UserApproved` → `SendApprovalNotification` sends the approval email.

## Force password change

`User::$force_password_change = true` redirects the user to a change-password page on every login until they comply. Set by admins via `EditUser`.

`EnsurePasswordChangeRequired` middleware enforces this at the Filament panel level.

## Activity log events

| Event | Log name |
|---|---|
| User created | `users` |
| Roles updated | `users` |
| Account approved | `users` |
| Password change required set | `users` |

## Policy

`App\Policies\ProfilePolicy` — governs profile view/update/password change for the current user.

Role and permission management is governed by Filament Shield's auto-generated permissions.
