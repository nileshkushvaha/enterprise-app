<?php

namespace App\Models;

use App\Enums\Navigation\NavigationLayoutType;
use App\Enums\Navigation\NavigationLocation;
use App\Enums\Navigation\NavigationStatus;
use Database\Factories\NavigationMenuFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class NavigationMenu extends Model
{
    /** @use HasFactory<NavigationMenuFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $table = 'navigations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
        'location',
        'layout_type',
        'status',
        'description',
        'locale',
        'settings',
    ];

    protected $casts = [
        'location' => NavigationLocation::class,
        'layout_type' => NavigationLayoutType::class,
        'status' => NavigationStatus::class,
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (NavigationMenu $menu) {
            $menu->created_by = auth()->id() ?? null;
        });

        static::updating(function (NavigationMenu $menu) {
            $menu->updated_by = auth()->id() ?? null;
        });
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(NavigationItem::class, 'navigation_id')->orderBy('_lft');
    }

    public function rootItems(): HasMany
    {
        return $this->hasMany(NavigationItem::class, 'navigation_id')
            ->whereNull('parent_id')
            ->orderBy('_lft');
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

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', NavigationStatus::Published);
    }

    public function scopeForLocation(Builder $query, NavigationLocation|string $location): Builder
    {
        return $query->where('location', $location instanceof NavigationLocation ? $location->value : $location);
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where(function (Builder $q) use ($locale) {
            $q->where('locale', $locale)->orWhereNull('locale');
        });
    }

    // ── Activity Log ──────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'location', 'layout_type', 'status', 'locale'])
            ->useLogName('navigation')
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }
}
