# Enterprise CMS – Phase 1: Page Management Foundation

A scalable, enterprise-ready page management system built with Laravel 13, Filament, and modern Laravel packages.

## 📋 Overview

This foundation module is designed for **static pages only** (Home, About, Contact, Privacy Policy, etc.) and is architected to support a future **Block Builder** without storing HTML directly.

### Architecture Principles

- **Pages own Blocks**: Each page contains multiple content blocks
- **No Direct HTML**: Content is stored as structured JSON, enabling future rendering flexibility
- **Block Ready**: Pre-prepared for rich content blocks (Hero, Rich Text, Gallery, Video, CTA, FAQ, etc.)
- **Activity Tracked**: All page changes are logged and tracked
- **Media Ready**: Integrated with Spatie Media Library for featured images
- **SEO Optimized**: Comprehensive SEO fields and metadata

## 🗄️ Database Schema

### Pages Table

```sql
CREATE TABLE pages (
  id UUID PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) UNIQUE NOT NULL,
  excerpt TEXT,
  featured_image -- (via media library)
  template VARCHAR(255) DEFAULT 'default',
  layout VARCHAR(255) DEFAULT 'default',
  status ENUM('draft', 'published', 'scheduled', 'archived') DEFAULT 'draft',
  visibility ENUM('public', 'private') DEFAULT 'private',
  published_at DATETIME,
  meta_title VARCHAR(70),
  meta_description VARCHAR(160),
  meta_keywords TEXT,
  canonical_url VARCHAR(255),
  robots VARCHAR(255),
  created_by BIGINT,
  updated_by BIGINT,
  timestamps,
  softDeletes
)
```

### Page Blocks Table

```sql
CREATE TABLE page_blocks (
  id UUID PRIMARY KEY,
  page_id UUID (foreign key to pages),
  block_type VARCHAR(255),
  content JSON,
  settings JSON,
  sort_order SMALLINT DEFAULT 0,
  is_active BOOLEAN DEFAULT true,
  timestamps,
  softDeletes
)
```

## 📦 Models

### Page Model

**Location**: `app/Models/Page.php`

**Features**:
- UUID primary key
- Soft deletes
- Media library integration (featured images)
- Activity logging
- Relationships: `hasMany('blocks')`
- Scopes: `published()`, `draft()`, `scheduled()`, `archived()`, `search()`, `byTemplate()`
- Helper methods: `isPublished()`, `isScheduled()`, `publish()`, `unpublish()`, `archive()`

**Enums**:
- `PageStatus`: Draft, Published, Scheduled, Archived
- `PageVisibility`: Public, Private

```php
// Create a page
$page = Page::create([
    'title' => 'Home',
    'slug' => 'home',
    'status' => PageStatus::Published,
    'visibility' => PageVisibility::Public,
]);

// Get published pages
$pages = Page::published()->get();

// Add blocks
$page->blocks()->create([
    'block_type' => 'hero',
    'content' => ['title' => '...', 'image' => '...'],
    'sort_order' => 0,
]);
```

### PageBlock Model

**Location**: `app/Models/PageBlock.php`

**Features**:
- UUID primary key
- Soft deletes
- Activity logging
- Relationships: `belongsTo('page')`
- Scopes: `active()`, `byType()`, `forPage()`, `ordered()`

```php
// Get active blocks for a page
$blocks = $page->activeBlocks()->ordered()->get();

// Create a block
$block = PageBlock::create([
    'page_id' => $page->id,
    'block_type' => 'hero',
    'content' => [...],
    'settings' => [...],
    'sort_order' => 0,
]);
```

## 🎭 Filament Admin

**Resource**: `PageResource` at `/admin/pages`

### Form (Tabs)

#### General Tab
- **Title** (required, max 255 chars) → Auto-generates slug
- **Slug** (required, unique, auto-generated, regex validated)
- **Excerpt** (optional, max 500 chars)
- **Featured Image** (media library, up to 5MB)
- **Template** (default, landing, blank)
- **Layout** (default, sidebar-left, sidebar-right, full-width)

#### Publishing Tab
- **Status** (Draft, Published, Scheduled, Archived)
- **Visibility** (Public, Private)
- **Published At** (datetime picker)

#### SEO Tab
- **Meta Title** (max 70 chars, recommended 50-70)
- **Meta Description** (max 160 chars, recommended 150-160)
- **Meta Keywords** (comma-separated)
- **Canonical URL** (optional URL)
- **Robots** (index/follow options)

### Table

**Columns**:
- Featured Image (thumbnail)
- Title (searchable, sortable)
- Slug (searchable, copyable)
- Template (badge)
- Status (colored badge)
- Visibility (colored badge)
- Published At (sortable)
- Updated At (toggleable)

**Filters**:
- Status (multi-select)
- Template (multi-select)
- Visibility (multi-select)
- Published (quick filter)
- Trashed (restore/delete)

**Actions** (per-record):
- Edit
- Publish (if not published)
- Unpublish (if published)
- Duplicate
- Archive
- Delete
- Restore

**Bulk Actions**:
- Delete
- Force Delete
- Restore

## 🔐 Permissions

**Setup**: Run `php artisan db:seed --class=PagePermissionSeeder`

| Permission | Description |
|-----------|-------------|
| `pages.list` | List all pages |
| `pages.view` | View page details |
| `pages.create` | Create new pages |
| `pages.update` | Edit pages |
| `pages.delete` | Delete pages |
| `pages.publish` | Publish/unpublish pages |

**Roles**:
- **Admin**: All permissions
- **Editor**: All except delete

## 📝 Services & Actions

### PageService

**Location**: `app/Services/PageService.php`

```php
use App\Services\PageService;

$service = app(PageService::class);

// Get pages with filters
$pages = $service->getPages(
    status: 'published',
    template: 'default',
    search: 'home'
);

// Get published pages
$published = $service->getPublishedPages(perPage: 15);

// Duplicate a page
$newPage = $service->duplicatePage($page);

// Publish/unpublish/archive
$service->publishPage($page);
$service->unpublishPage($page);
$service->archivePage($page);
```

### GeneratePageSlugAction

**Location**: `app/Actions/GeneratePageSlugAction.php`

```php
use App\Actions\GeneratePageSlugAction;

$action = app(GeneratePageSlugAction::class);
$slug = $action->execute('My Page Title'); // "my-page-title"
$slug = $action->execute('My Page', excludeId: $existingPageId); // "my-page-1"
```

## 📊 Activity Logging

All page and block changes are automatically logged:

- **Created**: When a page/block is created
- **Updated**: When attributes change
- **Restored**: When soft-deleted items are restored
- **Deleted**: When soft-deleted

**View logs**:
```php
$page->activities()->latest()->get();
// Get by type
$page->activities()->where('event', 'created')->get();
```

## 📷 Media Library Integration

Featured images are stored via Spatie Media Library in the `featured-image` collection:

```php
// Get featured image URL
$url = $page->getFirstMediaUrl('featured-image');

// Add featured image
$page->addMediaFromRequest('featured_image')
    ->toMediaCollection('featured-image');

// Delete featured image
$page->clearMediaCollection('featured-image');
```

## 🧪 Testing & Factories

### Page Factory

```php
use Database\Factories\PageFactory;

// Default page
$page = Page::factory()->create();

// Published page
$page = Page::factory()->published()->create();

// Draft page
$page = Page::factory()->draft()->create();

// Scheduled page
$page = Page::factory()->scheduled()->create();

// Archived page
$page = Page::factory()->archived()->create();
```

### PageBlock Factory

```php
use Database\Factories\PageBlockFactory;

// Default block
$block = PageBlock::factory()->create();

// Block with specific type
$block = PageBlock::factory()->ofType('hero')->create();

// Block for specific page
$block = PageBlock::factory()->forPage($page)->create();

// Combine
$block = PageBlock::factory()
    ->ofType('cta')
    ->forPage($page)
    ->create();
```

## 🚀 Usage Examples

### Create a Published Page

```php
use App\Models\Page;
use App\Enums\PageStatus;
use App\Enums\PageVisibility;

$page = Page::create([
    'title' => 'About Us',
    'slug' => 'about-us',
    'excerpt' => 'Learn about our company',
    'template' => 'default',
    'layout' => 'default',
    'status' => PageStatus::Published,
    'visibility' => PageVisibility::Public,
    'published_at' => now(),
    'meta_title' => 'About Us | Company Name',
    'meta_description' => 'Learn more about our company and mission.',
    'robots' => 'index, follow',
]);

// Add featured image
$page->addMediaFromUrl('https://example.com/image.jpg')
    ->toMediaCollection('featured-image');

// Add blocks
$page->blocks()->create([
    'block_type' => 'hero',
    'content' => [
        'title' => 'About Our Company',
        'subtitle' => 'Building amazing things',
        'image' => 'url...',
    ],
    'sort_order' => 0,
]);

$page->blocks()->create([
    'block_type' => 'rich_text',
    'content' => [
        'text' => '<p>Our company story...</p>',
    ],
    'sort_order' => 1,
]);
```

### Query Examples

```php
// Get published pages
Page::published()->get();

// Get by template
Page::byTemplate('landing')->get();

// Search pages
Page::search('about')->get();

// Get with blocks
$page = Page::with('blocks:page_id,block_type,sort_order')
    ->where('slug', 'about')
    ->first();

// Get active blocks
$blocks = $page->activeBlocks()->ordered()->get();

// Get specific block types
$heroes = $page->blocks()->byType('hero')->get();
```

## 🔧 Configuration

### MediaLibrary Collection

Featured images are stored in the `featured-image` collection with:
- Single file per page (singleFile)
- Accepts: JPEG, PNG, GIF, WebP
- Max size: 5MB
- Fallback image: `/images/placeholder.png`

### Activity Logging

Logs are stored in the `activity_log` table with:
- Log name: `pages` or `page_blocks`
- Only dirty attributes are logged
- Timestamp changes excluded

## 📅 Future Enhancements

This foundation is ready for:

- **Block Builder UI**: Drag-and-drop interface to manage blocks
- **Additional Block Types**: Implement the planned block types
- **Frontend Rendering**: Create rendering logic from JSON blocks
- **Publishing Workflows**: Approval process before publishing
- **Page Versioning**: Track page history
- **Template System**: Custom page templates
- **Page Scheduling**: Scheduled publishing with queue

## 📝 Migration History

1. `2026_06_27_065804`: Create pages table
2. `2026_06_27_070110`: Create page_blocks table
3. `2026_06_27_070746`: Update activity_log to support UUIDs

## 🧩 File Structure

```
app/
├── Enums/
│   ├── PageStatus.php
│   └── PageVisibility.php
├── Models/
│   ├── Page.php
│   └── PageBlock.php
├── Policies/
│   ├── PagePolicy.php
│   └── PageBlockPolicy.php
├── Services/
│   └── PageService.php
├── Actions/
│   └── GeneratePageSlugAction.php
└── Filament/Resources/Pages/
    ├── PageResource.php
    ├── Pages/
    │   ├── CreatePage.php
    │   ├── EditPage.php
    │   └── ListPages.php
    ├── Schemas/
    │   └── PageForm.php
    └── Tables/
        └── PagesTable.php

database/
├── factories/
│   ├── PageFactory.php
│   └── PageBlockFactory.php
├── migrations/
│   ├── 2026_06_27_065804_create_pages_table.php
│   ├── 2026_06_27_070110_create_page_blocks_table.php
│   └── 2026_06_27_070746_update_activity_log_subject_id_to_uuid.php
└── seeders/
    └── PagePermissionSeeder.php
```

## 🎯 Next Steps

- Add PageBlock Filament resource when block builder is ready
- Implement frontend routing and rendering
- Create block type validation system
- Add page preview functionality
- Setup page publishing workflows
