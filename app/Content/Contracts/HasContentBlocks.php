<?php

declare(strict_types=1);

namespace App\Content\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Contract for any content type that owns ContentBlocks.
 *
 * Implement this interface on Pages, Posts, and any future content type
 * (Documentation, Knowledge Base, Landing Pages, Products, News, FAQs).
 * The ContentBlockService, ContentRenderer, and ContentBlockObserver all
 * accept HasContentBlocks so they work for every implementor without changes.
 */
interface HasContentBlocks
{
    /**
     * All blocks owned by this content item, ordered by sort_order.
     */
    public function blocks(): MorphMany;

    /**
     * Only the active (visible) blocks for this content item.
     */
    public function activeBlocks(): MorphMany;
}
