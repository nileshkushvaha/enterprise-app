<?php

namespace App\Services;

use App\Content\Models\ContentBlock;
use App\Content\Services\ContentBlockService;
use App\Enums\BlockType;
use App\Models\Page;
use Illuminate\Support\Collection;

/**
 * Backward-compatible facade delegating to ContentBlockService.
 *
 * Retained so that existing Filament resource code, tests, and any
 * external callsites continue to resolve `app(BlockService::class)`
 * without changes. All logic lives in ContentBlockService.
 */
class BlockService
{
    public function __construct(
        private readonly ContentBlockService $inner,
    ) {}

    public function createBlock(Page $page, string|BlockType $blockType, array $content, array $settings = [], ?int $sortOrder = null): ContentBlock
    {
        return $this->inner->createBlock($page, $blockType, $content, $settings, $sortOrder);
    }

    public function updateBlock(ContentBlock $block, array $content, array $settings = []): bool
    {
        return $this->inner->updateBlock($block, $content, $settings);
    }

    public function reorderBlocks(array $blockIds): void
    {
        $this->inner->reorderBlocks($blockIds);
    }

    public function moveBlock(ContentBlock $block, int $newPosition): bool
    {
        return $this->inner->moveBlock($block, $newPosition);
    }

    public function duplicateBlock(ContentBlock $block, ?int $sortOrder = null): ContentBlock
    {
        return $this->inner->duplicateBlock($block, $sortOrder);
    }

    public function deleteBlock(ContentBlock $block): bool
    {
        return $this->inner->deleteBlock($block);
    }

    public function forceDeleteBlock(ContentBlock $block): bool
    {
        return $this->inner->forceDeleteBlock($block);
    }

    public function restoreBlock(ContentBlock $block): bool
    {
        return $this->inner->restoreBlock($block);
    }

    public function toggleBlockActive(ContentBlock $block): bool
    {
        return $this->inner->toggleBlockActive($block);
    }

    public function getPageBlocks(Page $page, bool $activeOnly = false): Collection
    {
        return $this->inner->getBlocks($page, $activeOnly);
    }

    public function getDefaultContent(BlockType $type): array
    {
        return $this->inner->getDefaultContent($type);
    }

    public function getDefaultSettings(BlockType $type): array
    {
        return $this->inner->getDefaultSettings($type);
    }
}
