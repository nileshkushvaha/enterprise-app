# Phase 2 – Enterprise CMS Page Block Management

## Overview

Phase 2 builds on the foundation of Phase 1 to implement a comprehensive block management system for pages. This includes:

- Block type enumeration with 18 pre-defined block types
- Block service layer for CRUD operations and reordering
- Filament PageBlockResource for managing blocks
- Block builder UI integrated into the PageResource
- Block content validation
- Activity logging for block operations

## Architecture

### Block System

Blocks are modular content units that compose a page:

```
Page (1)
  ├── Block (1+)
  │   ├── block_type: hero, image, cta, etc.
  │   ├── content: JSON data
  │   ├── settings: JSON configuration
  │   ├── sort_order: display order
  │   ├── is_active: visibility toggle
```

### Block Types (18 Total)

**Layout** (3)

- Hero: Large banner with title, subtitle, image, CTA
- Divider: Visual separator
- Spacer: Whitespace element

**Content** (2)

- Rich Text: Formatted text content
- Button: Single interactive button

**Media** (3)

- Image: Single image with caption
- Gallery: Multiple images in grid
- Video: Embedded video player

**Call to Action** (1)

- CTA: Section with heading, text, button

**Interactive** (3)

- FAQ: Collapsible Q&A pairs
- Accordion: Expandable sections
- Tabs: Tabbed content

**Components** (4)

- Team: Member profiles
- Testimonials: Reviews/feedback
- Statistics: Metrics display
- Timeline: Event chronology

**Location** (1)

- Map: Map/location display

**Forms** (1)

- Contact Form: Configurable form fields

## File Structure

```
app/
├── Enums/
│   └── BlockType.php                    # 18 block types with metadata
├── Services/
│   └── BlockService.php                 # Block CRUD & operations
├── Actions/
│   └── ValidateBlockContentAction.php   # Content validation
└── Filament/
    └── Resources/
        ├── PageBlocks/
        │   ├── PageBlockResource.php
        │   ├── Pages/
        │   │   ├── ListPageBlocks.php
        │   │   ├── CreatePageBlock.php
        │   │   └── EditPageBlock.php
        │   ├── Schemas/
        │   │   └── PageBlockForm.php
        │   └── Tables/
        │       └── PageBlocksTable.php
        └── Pages/
            └── Schemas/
                └── PageForm.php         # Updated with Blocks tab
```

## Key Components

### 1. BlockType Enum

```php
BlockType::Hero->label()      // "Hero"
BlockType::Hero->icon()       // "heroicon-m-photo"
BlockType::Hero->category()   // "Layout"
BlockType::Hero->color()      // "info"
BlockType::grouped()          // Array of types by category
```

**Categories:**

- Layout
- Content
- Media
- Call to Action
- Interactive
- Components
- Location
- Forms

### 2. BlockService

**Methods:**

```php
// CRUD Operations
createBlock(Page $page, string $blockType, array $content, array $settings = [], ?int $sortOrder = null): PageBlock
updateBlock(PageBlock $block, array $content, array $settings = []): bool
deleteBlock(PageBlock $block): bool

// Ordering
reorderBlocks(array $blockIds): void
moveBlock(PageBlock $block, int $newPosition): bool

// Utilities
duplicateBlock(PageBlock $block, ?int $sortOrder = null): PageBlock
toggleBlockActive(PageBlock $block): bool
getDefaultContent(BlockType $blockType): array
getDefaultSettings(BlockType $blockType): array
```

**Example Usage:**

```php
$blockService = app(BlockService::class);

// Create a hero block
$heroBlock = $blockService->createBlock(
    page: $page,
    blockType: 'hero',
    content: [
        'title' => 'Welcome',
        'subtitle' => 'Amazing content',
        'image' => 'hero.jpg',
        'button_text' => 'Learn More',
        'button_link' => '/about'
    ],
    settings: [
        'background_color' => '#ffffff',
        'text_color' => '#000000'
    ]
);

// Reorder blocks
$blockService->reorderBlocks(['block-1-id', 'block-2-id', 'block-3-id']);

// Duplicate a block
$duplicate = $blockService->duplicateBlock($heroBlock);
```

### 3. ValidateBlockContentAction

Validates block content based on block type:

```php
$validator = app(ValidateBlockContentAction::class);
$errors = $validator->execute(BlockType::Hero, $content);

// Returns array of error messages
// Empty array = valid
```

**Validation Rules:**

- Hero: requires title
- RichText: requires text
- Image: requires image URL
- Gallery: requires at least one image
- Video: requires URL
- CTA: requires title and button_link
- FAQ/Accordion/Tabs/Timeline: require items array
- Team/Testimonials/Statistics: require items array
- Button: requires text and link
- Spacer: requires height value
- Map: requires latitude and longitude
- ContactForm: requires form_fields array

### 4. Filament PageBlockResource

**Location:** `/admin/page-blocks`

**Features:**

- List all blocks with filtering by type
- Create/edit/delete blocks
- Duplicate blocks
- Soft delete with restore
- Columns: Type, Order, Preview, Active status, Timestamps
- Filters: Block type, Trashed status

**Form Tabs:**

- Content: Block type, JSON content
- Settings: JSON settings, Active toggle
- Ordering: Display order

### 5. Block Builder UI (PageResource)

**New Tab:** "Blocks" in the Page edit form

**Features:**

- Repeater field for managing blocks
- Add blocks with "Add Block" button
- Drag-and-drop reordering
- Collapse/expand blocks
- Block type label in header
- Inline editing of content/settings
- Delete blocks from repeater

**Form Fields per Block:**

- block_type: Select from all 18 types (grouped by category)
- sort_order: Numeric order value
- is_active: Toggle switch
- content: JSON textarea
- settings: JSON textarea

## Usage Guide

### Managing Pages and Blocks

#### Creating a Page with Blocks

1. Go to Admin → Pages
2. Click "New Page"
3. Fill in General tab (title, slug, excerpt, image)
4. Go to Blocks tab
5. Click "Add Block"
6. Select block type
7. Fill in block content (JSON)
8. Add more blocks as needed
9. Reorder by dragging
10. Save page

#### Editing Block Content

**Option 1: From PageResource Blocks Tab**

- Navigate to page edit form
- Go to Blocks tab
- Click block to expand
- Edit JSON content/settings
- Save page

**Option 2: From PageBlockResource**

- Navigate to Admin → Blocks
- Find block by page or type
- Click Edit
- Modify content/settings
- Save block

#### Block Content Examples

**Hero Block:**

```json
{
    "title": "Welcome to Our Site",
    "subtitle": "Amazing features await",
    "image": "hero.jpg",
    "button_text": "Get Started",
    "button_link": "/contact"
}
```

**FAQ Block:**

```json
{
    "items": [
        {
            "question": "How does it work?",
            "answer": "It's simple..."
        },
        {
            "question": "What's the price?",
            "answer": "We offer flexible pricing..."
        }
    ]
}
```

**Statistics Block:**

```json
{
    "items": [
        {
            "number": "10K",
            "label": "Happy Customers"
        },
        {
            "number": "500+",
            "label": "Projects Completed"
        }
    ]
}
```

## Database Schema

### page_blocks Table

| Column     | Type      | Notes                   |
| ---------- | --------- | ----------------------- |
| id         | uuid      | Primary key             |
| page_id    | uuid      | Foreign key to pages    |
| block_type | enum      | One of 18 types         |
| content    | json      | Block content data      |
| settings   | json      | Block styling/behavior  |
| sort_order | integer   | Display order (0-based) |
| is_active  | boolean   | Visibility toggle       |
| created_at | timestamp |                         |
| updated_at | timestamp |                         |
| deleted_at | timestamp | Soft delete             |

## Activity Logging

All block operations are logged:

- **Created** → When a new block is added
- **Updated** → When block content/settings change
- **Deleted** → When a block is deleted
- **Restored** → When a deleted block is restored

View logs in Admin → Activity Log

## Permissions

Block management uses page permissions:

- `pages.list` - View block listings
- `pages.create` - Create blocks
- `pages.update` - Update blocks
- `pages.delete` - Delete blocks
- `pages.view` - View block details

## Future Enhancements

1. **Rich Text Editor** - Replace JSON editing with UI forms per block type
2. **Block Preview** - Live preview of blocks in editor
3. **Block Templates** - Pre-configured block templates
4. **Content Versioning** - Version history for blocks
5. **Block Scheduling** - Scheduled block visibility
6. **A/B Testing** - Test different block variants
7. **Analytics** - Track block interaction/performance
8. **Frontend Rendering** - Blade components for block display

## Testing

Run tests:

```bash
php artisan test

# Specific test file
php artisan test tests/Feature/BlockServiceTest.php
```

**Test Coverage:**

- Block creation with various types
- Block reordering
- Block duplication
- Block deletion and restoration
- Content validation
- Activity logging
- Filament form rendering
- Permission checks

## Troubleshooting

### Issue: Blocks not appearing in page edit

**Solution:** Verify the Page model has `hasMany('blocks')` relationship

### Issue: JSON validation errors

**Solution:** Ensure JSON in content/settings is valid. Use online JSON validator.

### Issue: Sort order conflicts

**Solution:** BlockService automatically recalculates sort_order on reorder

### Issue: Permissions denied error

**Solution:** Ensure user has `pages.view` and `pages.update` permissions

## API Reference

### BlockService Methods

```php
// Create
createBlock(Page, string, array, array, ?int): PageBlock

// Read
getDefaultContent(BlockType): array
getDefaultSettings(BlockType): array

// Update
updateBlock(PageBlock, array, array): bool
toggleBlockActive(PageBlock): bool

// Delete
deleteBlock(PageBlock): bool

// Reorder
reorderBlocks(array): void
moveBlock(PageBlock, int): bool

// Utility
duplicateBlock(PageBlock, ?int): PageBlock
```

### ValidateBlockContentAction

```php
execute(BlockType, array): array // Returns error messages
```

## Related Documents

- PAGES_FOUNDATION.md - Phase 1 foundation documentation
- PHASE1_COMPLETION.md - Phase 1 completion summary

## Support

For issues or questions:

1. Check troubleshooting section
2. Review test files for examples
3. Check activity logs for error details
4. Review Filament documentation
