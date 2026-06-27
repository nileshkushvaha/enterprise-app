<?php

namespace App\Models;

use App\Enums\BlockType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PageBlock extends Model
{
    /** @use HasFactory<\Database\Factories\PageBlockFactory> */
    use HasFactory, HasUuids, SoftDeletes, LogsActivity;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'page_id',
        'block_type',
        'content',
        'settings',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'block_type' => BlockType::class,
        'content' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the page that owns the block
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Scope: Get active blocks
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by block type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('block_type', $type);
    }

    /**
     * Scope: Get blocks for a specific page
     */
    public function scopeForPage(Builder $query, string $pageId): Builder
    {
        return $query->where('page_id', $pageId);
    }

    /**
     * Scope: Ordered by sort order
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Activity Log settings
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'block_type',
                'content',
                'settings',
                'sort_order',
                'is_active',
            ])
            ->useLogName('page_blocks')
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }
}

