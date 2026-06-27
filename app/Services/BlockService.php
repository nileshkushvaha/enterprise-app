<?php

namespace App\Services;

use App\Enums\BlockType;
use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Support\Collection;

class BlockService
{
    /**
     * Create a block for a page
     */
    public function createBlock(Page $page, string $blockType, array $content, array $settings = [], ?int $sortOrder = null): PageBlock
    {
        // Get next sort order if not provided
        if ($sortOrder === null) {
            $sortOrder = $page->blocks()->max('sort_order') + 1 ?? 0;
        }

        return $page->blocks()->create([
            'block_type' => $blockType,
            'content' => $content,
            'settings' => $settings,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    /**
     * Update a block
     */
    public function updateBlock(PageBlock $block, array $content, array $settings = []): bool
    {
        return $block->update([
            'content' => $content,
            'settings' => $settings,
        ]);
    }

    /**
     * Reorder blocks
     */
    public function reorderBlocks(array $blockIds): void
    {
        foreach ($blockIds as $index => $blockId) {
            PageBlock::where('id', $blockId)->update([
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Move block to new position
     */
    public function moveBlock(PageBlock $block, int $newPosition): bool
    {
        $page = $block->page;
        $totalBlocks = $page->blocks()->count();

        if ($newPosition < 0 || $newPosition >= $totalBlocks) {
            return false;
        }

        // Get current position
        $currentPosition = $block->sort_order;

        if ($currentPosition === $newPosition) {
            return true;
        }

        // Reorder blocks
        $blocks = $page->blocks()
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($b) => $b->id !== $block->id)
            ->values();

        $blocks->splice($newPosition, 0, [$block]);

        foreach ($blocks as $index => $b) {
            $b->update(['sort_order' => $index]);
        }

        return true;
    }

    /**
     * Duplicate a block
     */
    public function duplicateBlock(PageBlock $block, ?int $sortOrder = null): PageBlock
    {
        if ($sortOrder === null) {
            $sortOrder = $block->page->blocks()->max('sort_order') + 1 ?? 0;
        }

        return $block->page->blocks()->create([
            'block_type' => $block->block_type,
            'content' => $block->content,
            'settings' => $block->settings,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    /**
     * Delete a block
     */
    public function deleteBlock(PageBlock $block): bool
    {
        return (bool) $block->delete();
    }

    /**
     * Force delete a block
     */
    public function forceDeleteBlock(PageBlock $block): bool
    {
        return (bool) $block->forceDelete();
    }

    /**
     * Restore a block
     */
    public function restoreBlock(PageBlock $block): bool
    {
        return (bool) $block->restore();
    }

    /**
     * Toggle block active status
     */
    public function toggleBlockActive(PageBlock $block): bool
    {
        return $block->update([
            'is_active' => !$block->is_active,
        ]);
    }

    /**
     * Get all blocks for a page ordered
     */
    public function getPageBlocks(Page $page, bool $activeOnly = false): Collection
    {
        $query = $page->blocks();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')->get();
    }

    /**
     * Get default content for a block type
     */
    public function getDefaultContent(BlockType $type): array
    {
        return match ($type) {
            BlockType::Hero => [
                'title' => 'Enter your title here',
                'subtitle' => 'Enter your subtitle here',
                'image' => null,
                'button_text' => 'Get Started',
                'button_link' => '#',
                'button_style' => 'primary',
            ],
            BlockType::RichText => [
                'text' => '<p>Enter your rich text content here</p>',
            ],
            BlockType::Image => [
                'image' => null,
                'caption' => '',
                'alt_text' => '',
            ],
            BlockType::Gallery => [
                'images' => [],
                'columns' => 3,
                'spacing' => 'medium',
            ],
            BlockType::Video => [
                'url' => '',
                'title' => '',
                'description' => '',
                'autoplay' => false,
                'loop' => false,
            ],
            BlockType::CTA => [
                'title' => 'Call To Action',
                'description' => 'Enter your CTA description',
                'button_text' => 'Learn More',
                'button_link' => '#',
                'button_style' => 'primary',
                'image' => null,
            ],
            BlockType::FAQ => [
                'items' => [
                    ['question' => 'Sample question?', 'answer' => 'Sample answer.'],
                ],
            ],
            BlockType::Accordion => [
                'items' => [
                    ['title' => 'Section 1', 'content' => 'Content for section 1'],
                ],
            ],
            BlockType::Tabs => [
                'tabs' => [
                    ['label' => 'Tab 1', 'content' => 'Content for tab 1'],
                ],
            ],
            BlockType::Team => [
                'members' => [],
                'columns' => 3,
            ],
            BlockType::Testimonials => [
                'testimonials' => [],
                'style' => 'card',
            ],
            BlockType::Statistics => [
                'items' => [],
                'columns' => 3,
            ],
            BlockType::Timeline => [
                'items' => [],
                'direction' => 'vertical',
            ],
            BlockType::Button => [
                'text' => 'Click Me',
                'link' => '#',
                'style' => 'primary',
                'size' => 'medium',
            ],
            BlockType::Divider => [
                'style' => 'solid',
                'color' => '#000000',
                'height' => 1,
            ],
            BlockType::Spacer => [
                'height' => 40,
            ],
            BlockType::Map => [
                'latitude' => 0,
                'longitude' => 0,
                'zoom' => 13,
                'marker_title' => 'Location',
            ],
            BlockType::ContactForm => [
                'form_fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
                ],
                'submit_button' => 'Send Message',
                'success_message' => 'Thank you for your message!',
            ],
        };
    }

    /**
     * Get default settings for a block type
     */
    public function getDefaultSettings(BlockType $type): array
    {
        return [
            'background_color' => null,
            'text_color' => null,
            'padding' => 'medium',
            'margin' => 'medium',
            'text_alignment' => 'left',
            'animation' => 'none',
            'animation_delay' => 0,
            'container_width' => 'full',
            'custom_class' => '',
        ];
    }
}
