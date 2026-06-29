# Pages

## Model

`App\Models\Page` — UUID primary key, soft deletes, `LogsActivity` trait.

Log name: `pages`. Tracked fields: `title`, `slug`, `status`, `visibility`, `template`, `published_at`, `meta_*`.

Implements `HasContentBlocks` contract — blocks accessible via `$page->blocks()` morph relation.

Status values: `draft`, `published`, `scheduled`, `archived`. Status managed by `PageStatus` enum.

## Lifecycle

- **Create** → `PageObserver::created()` fires
- **Update** → `PageObserver::updated()` fires, checks for scheduled publish transition
- **Soft delete** → content blocks are not deleted (orphan-safe)
- **Scheduled publish** → `PublishScheduledContent` artisan command runs every minute, auto-publishes pages where `published_at <= now()` and `status = scheduled`

## SEO fields

`meta_title`, `meta_description`, `meta_keywords`, `canonical_url`, `robots`, `og_image`. Managed by `SeoManager`. Priority: Page fields → `SeoSettings` → defaults.

## Preview

`Admin\PagePreviewController` — renders the page without publishing. Logs `previewed` event to `activity('pages')`.

## Frontend rendering

```
PageController::show($slug)
→ PageService::getPublishedPage($slug)
→ PageRenderService::render($page)    ← singleton
→ Blade view 'frontend.page'
```

## Filament Resource

`app/Filament/Resources/Pages/` — CMS group.

Includes block editor (drag-and-drop ordering of content blocks by type).
