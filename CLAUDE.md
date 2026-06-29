# Enterprise App

## Stack

- Laravel 13 · PHP 8.5 · MySQL
- Filament v4 · Admin panel at `/admin`
- Spatie Permission · Spatie Activitylog · Spatie Laravel Settings · Spatie Media Library
- Kalnoy NestedSet (navigation tree)

## Architecture

```
Controller → FormRequest → Service → Repository → Model
```

## Rules

- Never modify vendor packages or package migrations
- Keep Filament Resources thin — logic belongs in Services
- Services contain business logic
- Repositories contain database queries
- Policies handle authorization
- Activity Log is the Audit Trail — use `AuditTrailService`, never `activity()` directly in business code
- Notifications originate from the Activity Log pipeline, never from Services directly
- Queue heavy work
- Reuse existing Services, Repositories, Policies, Settings before creating new ones
- No duplicate business logic

## Before coding

1. Read `docs/index.md`
2. Read only the relevant module doc
3. Make the smallest possible change
4. Do not refactor unrelated code
