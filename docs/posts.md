# Posts

## Model

`App\Models\Post` — UUID primary key, `LogsActivity` trait.

Log name: `posts`. Tracked fields: `title`, `slug`, `excerpt`, `author_id`, `status`, `visibility`, `published_at`, `featured`, `allow_comments`, `meta_*`.

Relations: `author` (User), `categories` (PostCategory, many-to-many), `tags` (Tag, many-to-many), `relatedPosts` (self many-to-many), blocks via `HasContentBlocks`.

Status: `draft`, `published`, `scheduled`, `archived`.

## Lifecycle

- Scheduled publish: `PublishScheduledContent` command runs every minute, auto-publishes posts with `published_at <= now()` and `status = scheduled`. Logs `cms.auto_published` event → triggers admin notification.
- `PostObserver` fires on create/update/delete and logs to `activity('posts')`.

## Categories

`App\Models\PostCategory` — hierarchical with `parent_id`. Not NestedSet — simple adjacency list.

## Tags

`App\Models\Tag` — flat, many-to-many pivot with posts.

## Duplication

Posts support duplication (copy with new slug). `PostObserver` handles duplication lifecycle.

## Filament Resource

`app/Filament/Resources/Posts/` — CMS group. Includes block editor, related posts selector, category/tag pickers.
