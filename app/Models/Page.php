<?php

namespace App\Models;

use App\Enums\PageStatus;
use App\Enums\PageVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Page extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\PageFactory> */
    use HasFactory, HasUuids, SoftDeletes, LogsActivity, InteractsWithMedia;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'template',
        'layout',
        'status',
        'visibility',
        'published_at',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'robots',
    ];

    protected $casts = [
        'status' => PageStatus::class,
        'visibility' => PageVisibility::class,
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Page $page) {
            $page->created_by = auth()->id() ?? null;
        });

        static::updating(function (Page $page) {
            $page->updated_by = auth()->id() ?? null;
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Register Media Collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured-image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->useFallbackUrl(url('/images/placeholder.png'));
    }

    /**
     * Get featured image URL
     */
    public function featuredImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getFirstMediaUrl('featured-image')
        );
    }

    /**
     * Get the user who created the page
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the page
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the page blocks
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class)->orderBy('sort_order');
    }

    /**
     * Get active blocks
     */
    public function activeBlocks(): HasMany
    {
        return $this->blocks()->where('is_active', true);
    }

    /**
     * Scope: Get published pages
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PageStatus::Published)
            ->where('visibility', PageVisibility::Public)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Scope: Get draft pages
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', PageStatus::Draft);
    }

    /**
     * Scope: Get scheduled pages
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', PageStatus::Scheduled)
            ->where('published_at', '<=', now());
    }

    /**
     * Scope: Get archived pages
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', PageStatus::Archived);
    }

    /**
     * Scope: Filter by template
     */
    public function scopeByTemplate(Builder $query, string $template): Builder
    {
        return $query->where('template', $template);
    }

    /**
     * Scope: Search across title, slug, and excerpt
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term): void {
            $query->where('title', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%");
        });
    }

    /**
     * Check if page is published
     */
    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published && $this->visibility === PageVisibility::Public;
    }

    /**
     * Check if page is scheduled
     */
    public function isScheduled(): bool
    {
        return $this->status === PageStatus::Scheduled && $this->published_at && $this->published_at <= now();
    }

    /**
     * Publish the page
     */
    public function publish(): bool
    {
        return $this->update([
            'status' => PageStatus::Published,
            'visibility' => PageVisibility::Public,
            'published_at' => now(),
        ]);
    }

    /**
     * Unpublish the page
     */
    public function unpublish(): bool
    {
        return $this->update([
            'status' => PageStatus::Draft,
            'visibility' => PageVisibility::Private,
        ]);
    }

    /**
     * Archive the page
     */
    public function archive(): bool
    {
        return $this->update([
            'status' => PageStatus::Archived,
        ]);
    }

    /**
     * Invalidate render cache
     */
    public function invalidateRenderCache(): void
    {
        app(\App\Services\PageRenderService::class)->invalidateCache($this);
    }

    /**
     * Activity Log settings
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'title',
                'slug',
                'excerpt',
                'status',
                'visibility',
                'published_at',
                'meta_title',
                'meta_description',
            ])
            ->useLogName('pages')
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }
}
