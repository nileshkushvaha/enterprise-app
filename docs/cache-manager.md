# Cache Manager

## Overview

Admin page at `/admin/cache-manager`. System group, sort 1.

Provides 7 cache operations, each permission-gated.

## Operations

| Method | Artisan command | Permission |
|---|---|---|
| `clearApplicationCache()` | `cache:clear` | `cache_manager.clear` |
| `clearViewCache()` | `view:clear` | `cache_manager.clear` |
| `clearRouteCache()` | `route:clear` | `cache_manager.clear` |
| `clearConfigCache()` | `config:clear` | `cache_manager.clear` |
| `clearEventCache()` | `event:clear` | `cache_manager.clear` |
| `optimize()` | `optimize` | `cache_manager.optimize` |
| `optimizeClear()` | `optimize:clear` | `cache_manager.optimize` |

Page view is gated by `cache_manager.view`.

## Key files

| File | Purpose |
|---|---|
| `app/Services/CacheManagerService.php` | All cache operations, delegates to Artisan |
| `app/Filament/Pages/CacheManagerPage.php` | Filament page, System group sort 1 |
| `app/Policies/CacheManagerPolicy.php` | viewPage, clearApplicationCache, optimize |

## Activity logging

Every operation is logged via `AuditTrailService`:
- If an authenticated user triggered it: `logUser()`
- If triggered by a system process: `logSystem()`

Log name: `cache_manager`, event: `cleared`.
