<?php

namespace App\Models;

use App\Enums\Navigation\NavigationLinkType;
use App\Enums\Navigation\NavigationVisibility;
use Database\Factories\NavigationItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class NavigationItem extends Model
{
    /** @use HasFactory<NavigationItemFactory> */
    use HasFactory, HasUuids, LogsActivity, NodeTrait, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'navigation_id',
        'parent_id',
        'label',
        'link_type',
        'url',
        'route_name',
        'route_params',
        'linkable_type',
        'linkable_id',
        'target',
        'rel',
        'icon',
        'css_class',
        'css_id',
        'badge_text',
        'badge_color',
        'visibility',
        'sort_order',
        'is_active',
        'open_in_modal',
        'extra_attributes',
        'locale',
        'publish_from',
        'publish_until',
    ];

    protected $casts = [
        'link_type' => NavigationLinkType::class,
        'visibility' => NavigationVisibility::class,
        'route_params' => 'array',
        'extra_attributes' => 'array',
        'is_active' => 'boolean',
        'open_in_modal' => 'boolean',
        'sort_order' => 'integer',
        'depth' => 'integer',
        'publish_from' => 'datetime',
        'publish_until' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (NavigationItem $item) {
            $item->created_by = auth()->id() ?? null;
        });

        static::updating(function (NavigationItem $item) {
            $item->updated_by = auth()->id() ?? null;
        });
    }

    // ── NestedSet key override (UUID parent) ──────────────────────────────

    public function getParentIdName(): string
    {
        return 'parent_id';
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function navigation(): BelongsTo
    {
        return $this->belongsTo(NavigationMenu::class, 'navigation_id');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'navigation_item_roles', 'navigation_item_id', 'role_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'navigation_item_permissions', 'navigation_item_id', 'permission_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForNavigation(Builder $query, string $navigationId): Builder
    {
        return $query->where('navigation_id', $navigationId);
    }

    public function scopeVisibleTo(Builder $query, NavigationVisibility $visibility): Builder
    {
        return $query->where('visibility', $visibility);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    // ── Activity Log ──────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['label', 'link_type', 'url', 'visibility', 'is_active', 'sort_order'])
            ->useLogName('navigation_item')
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }
}
