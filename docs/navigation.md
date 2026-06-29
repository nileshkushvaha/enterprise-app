# Navigation

## Overview

The navigation system is a standalone bounded context in `app/Navigation/`. It is registered by `NavigationServiceProvider`.

## Key classes

| Class | Purpose |
|---|---|
| `NavigationManager` | Top-level orchestrator, resolves menus by location |
| `NavigationRepository` | Eloquent queries, eager-loads items + roles + permissions |
| `NavigationRenderer` | Converts DB tree to HTML via Blade components |
| `NavigationCacheManager` | Invalidates/warms cache (tagged: `navigation`) |
| `NavigationItemService` | Resolves `is_active` state for current URL |
| `PermissionEvaluator` | Visibility checks (roles, permissions, publish windows) |
| `UrlResolver` + `Drivers/` | 10 link type drivers |

## Models

`App\Models\NavigationMenu` — menu container (name, location slug).

`App\Models\NavigationItem` — uses **Kalnoy NestedSet** (`_lft`, `_rgt`, `parent_id`, `depth`). Never use raw adjacency list queries.

## 10 link types (`NavigationItem::$link_type`)

Page, Post, Route (named route), External URL, Anchor, Email, Phone, File, Custom, Separator.

Each type has a driver in `app/Navigation/Drivers/`.

## Item visibility

- Role-based: `NavigationItemRoles` pivot — item shown only to listed roles
- Permission-based: `NavigationItemPermissions` pivot — item shown only to users with listed permissions
- Publish windows: `published_at` / `expired_at` on items
- `PermissionEvaluator` checks all three before rendering

## Cache

Navigation trees are cached with tag `navigation`. Any mutation to `NavigationMenu` or `NavigationItem` (via observer) invalidates the cache. `NavigationCacheManager::warm()` pre-builds the cache.

## Admin

`app/Livewire/Navigation/MenuBuilder.php` — Livewire component for drag-and-drop tree editing.

Filament Resource: `app/Filament/Resources/NavigationMenus/` — CMS group.
