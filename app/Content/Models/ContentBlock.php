<?php

declare(strict_types=1);

namespace App\Content\Models;

use App\Enums\BlockType;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Unified content block model.
 *
 * Replaces the former PageBlock and PostBlock models. Any content type
 * that implements HasContentBlocks stores its blocks here via the
 * polymorphic (blockable_type / blockable_id) pair.
 *
 * Backward-compatible convenience relationships page() and post() allow
 * the existing Filament resources and Blade templates to keep referencing
 * $block->page and $block->post without changes, as long as the resource
 * is correctly scoped to one blockable_type.
 *
 * @property string      $id
 * @property string      $blockable_type
 * @property string      $blockable_id
 * @property BlockType   $block_type
 * @property array|null  $content
 * @property array|null  $settings
 * @property int         $sort_order
 * @property bool        $is_active
 */
class ContentBlock extends Model
{
    use HasFactory, HasUuids, SoftDeletes, LogsActivity;

    protected $table = 'content_blocks';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'blockable_type',
        'blockable_id',
        'block_type',
        'content',
        'settings',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'block_type'  => BlockType::class,
        'content'     => 'array',
        'settings'    => 'array',
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    /**
     * Polymorphic owner (Page, Post, or any future content type).
     */
    public function blockable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Backward-compat convenience accessor for page blocks.
     * Works correctly when the enclosing resource is scoped to Page blocks.
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'blockable_id');
    }

    /**
     * Backward-compat convenience accessor for post blocks.
     * Works correctly when the enclosing resource is scoped to Post blocks.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'blockable_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByType(Builder $query, BlockType|string $type): Builder
    {
        return $query->where('block_type', $type instanceof BlockType ? $type->value : $type);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function scopeForOwner(Builder $query, Model $owner): Builder
    {
        return $query
            ->where('blockable_type', $owner->getMorphClass())
            ->where('blockable_id', $owner->getKey());
    }

    // ── Activity Log ─────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['block_type', 'content', 'settings', 'sort_order', 'is_active'])
            ->useLogName('content_blocks')
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }
}
