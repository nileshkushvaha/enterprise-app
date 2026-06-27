<?php

namespace App\Observers;

use App\Content\Models\ContentBlock;
use App\Content\Rendering\ContentRenderer;
use App\Models\Page;
use App\Models\Post;
use App\Services\PostService;

/**
 * Unified observer replacing PageBlockObserver + PostBlockObserver.
 *
 * On any block lifecycle event:
 *  - Page blocks  → flush the page render cache
 *  - Post blocks  → refresh reading time + flush the post render cache
 */
class ContentBlockObserver
{
    public function created(ContentBlock $block): void
    {
        $this->handle($block);
    }

    public function updated(ContentBlock $block): void
    {
        $this->handle($block);
    }

    public function deleted(ContentBlock $block): void
    {
        $this->handle($block);
    }

    public function restored(ContentBlock $block): void
    {
        $this->handle($block);
    }

    private function handle(ContentBlock $block): void
    {
        $owner = $block->blockable;

        if (! $owner) {
            return;
        }

        $renderer = app(ContentRenderer::class);

        if ($owner instanceof Page) {
            $renderer->invalidateCache($owner);
        } elseif ($owner instanceof Post) {
            app(PostService::class)->refreshReadingTime($owner);
            $renderer->invalidatePostCache($owner);
        }
    }
}
