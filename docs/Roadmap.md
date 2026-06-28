# Roadmap

## Status: Feature Complete v1.0 — Stable Baseline

The framework is locked and stable. All framework tests pass (762/762). The codebase has been audited, dead code removed, and Pint applied. This is the baseline from which business modules are built.

---

## What's Built (v1.0)

### Auth Module ✅
- Email/password login with remember me
- Registration with email verification
- Password reset (token-based email flow)
- Account locking after N failed attempts (configurable via `LoginSecuritySettings`)
- Self-service account unlock via email token
- Two-factor authentication (TOTP + recovery codes)
- Suspicious login detection + notification
- Full login history tracking (`login_histories` table)

### Profile Module ✅
- Profile view/edit (name, bio, avatar, social links)
- Password change with current-password verification
- Active sessions list with revoke
- `TrackUserSession` middleware recording all sessions

### Security Module ✅ (6 admin pages)
- **Authentication** — login methods, remember me, email verification, OAuth placeholders
- **Password Policy** — min length, complexity rules, expiry, history (prevent reuse)
- **Login Security** — max attempts, lockout duration, 2FA configuration
- **Session** — driver, lifetime, single-device enforcement, force-logout-all
- **Registration** — open/closed registration, allowed email domains, default role
- **Account Protection** — auto-lock threshold, suspicious login alerts

All settings persist to DB via Spatie Settings. All pages gated with `view`/`update` permission separation. `PasswordRuleBuilder` is the single source of truth for password validation across the entire app.

### CMS Module ✅
- **Pages** — UUID PK, scheduled publishing, SEO fields, visibility, templates, soft deletes
- **Posts** — UUID PK, author, categories, tags, related posts, featured image, scheduled publishing
- **Content Blocks** — 19 block types (Hero, RichText, Image, Gallery, Video, CTA, FAQ, Accordion, Tabs, Team, Testimonials, Statistics, Timeline, Button, Divider, Spacer, Map, ContactForm, ContactInfo)
- **Navigation** — multi-menu system, 10 link types, role/permission visibility, publish windows
- **Activity Log** — all model changes recorded, viewable in admin
- **Search** — frontend full-text search (`SearchController`)
- **SEO** — sitemap.xml, robots.txt, per-page meta, OG, JSON-LD, `SeoManager`
- **Media** — Spatie Media Library integration (avatars, featured images)
- **Frontend contact form** — submission handling + email notification

### Settings Module ✅ (7 admin pages)
- General (app name, org, homepage selection)
- SEO defaults
- Mail (SMTP configuration)
- Payment — 4 pages (Gateways, Configuration, Advanced, Bank Account)

### System Module ✅
- **Cache Manager** — clear/optimize by cache type with permission gating
- **Scheduler Monitor** — view tasks, run on-demand, history (30-day TTL, pruned daily), mutex detection, cron description
- **Queue Monitor** — pending/failed job counts by queue

### Admin Panel ✅
- Filament v4 at `/admin`
- Navigation groups: Administration, CMS, Masters, Configuration, Payment, Security, System
- Role/permission management (Filament Shield)
- User management with status control
- Dashboard widgets: stats overview, recent users, recent logins

---

## Not Yet Built (Future Business Modules)

These are planned but have zero code — no stubs, no placeholders.

### E-Commerce / Payments
- Payment gateway integration (Stripe, Razorpay, PayPal, Cashfree, PayU, PhonePe)
- Webhook processing infrastructure is scaffolded (`PaymentWebhookController`, `ProcessPaymentWebhookJob`, `PaymentWebhookProcessor`, `PaymentWebhookSignatureService`)
- Products, orders, invoices, subscriptions — not started
- `PaymentSettingsNavigationPage` is intentionally hidden (`shouldRegisterNavigation = false`) — it's a grouping shell for the payment settings cluster

### Learning Management (LMS)
- Courses, lessons, enrollments — not started
- The `student` role exists (default registration role)

### Password History Enforcement
- `user_password_histories` table exists and migration is in place
- `PasswordPolicySettings::prevent_reuse` and `password_history_count` are configurable
- The enforcement logic (checking new passwords against history on change) is **not yet implemented**

### Two-Factor Settings
- `AuthenticationSettings` has 2FA-related fields (stored, not yet enforced)
- `User::RECOVERY_CODES_COUNT = 8` is a constant pending a future `TwoFactorSettings` class
- 2FA UI exists (`/two-factor/setup`, `/two-factor/challenge`), but admin-level 2FA enforcement config is not wired

### Tenant / Multi-Site
- Not planned in current architecture — single-tenant

---

## Known Technical Debt

| Item | Risk | Effort |
|---|---|---|
| 109 files missing `declare(strict_types=1)` | Low — cosmetic | Medium — add when touching files |
| `PaymentSettingsNavigationPage` purpose undocumented | Low — intentional stub | Trivial — document before payment work |
| Password history enforcement not implemented | Medium — admin can configure it, no effect | High — implement at password change time |
| Settings resolved via `app()` in enum methods | Low — cached by Spatie | Future — extract to service if settings become heavy |

---

## Starting a New Business Module

1. Read [Architecture.md](Architecture.md) to understand service provider and namespace conventions
2. Read [Coding-Standards.md](Coding-Standards.md) for the exact Filament resource pattern
3. Read [Permission-Matrix.md](Permission-Matrix.md) before adding new Gate abilities
4. Create settings class + migration if the module has configurable behavior
5. Run `php artisan shield:generate` after creating new Filament Resources
6. Run `composer test` — all 762 existing tests must remain green before you ship
