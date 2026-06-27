<?php

namespace App\Services;

use App\Enums\BlockType;
use App\Models\PageBlock;
use Illuminate\View\View;

/**
 * BlockRenderer handles conversion of stored block JSON to rendered HTML
 * via Blade components.
 */
class BlockRenderer
{
    private BlockContentHydrator $hydrator;

    public function __construct()
    {
        $this->hydrator = new BlockContentHydrator();
    }

    /**
     * Render a single block to HTML
     */
    public function render(PageBlock $block): string
    {
        return $this->renderBlock($block->block_type, $this->getBlockData($block))->render();
    }

    /**
     * Render a block to a View object (useful for testing/chaining)
     */
    public function renderBlock(BlockType $blockType, array $data): View
    {
        return match ($blockType) {
            BlockType::Hero => view('components.blocks.hero', $data),
            BlockType::RichText => view('components.blocks.rich-text', $data),
            BlockType::Image => view('components.blocks.image', $data),
            BlockType::Gallery => view('components.blocks.gallery', $data),
            BlockType::Video => view('components.blocks.video', $data),
            BlockType::CTA => view('components.blocks.cta', $data),
            BlockType::FAQ => view('components.blocks.faq', $data),
            BlockType::Accordion => view('components.blocks.accordion', $data),
            BlockType::Tabs => view('components.blocks.tabs', $data),
            BlockType::Team => view('components.blocks.team', $data),
            BlockType::Testimonials => view('components.blocks.testimonials', $data),
            BlockType::Statistics => view('components.blocks.statistics', $data),
            BlockType::Timeline => view('components.blocks.timeline', $data),
            BlockType::Button => view('components.blocks.button', $data),
            BlockType::Divider => view('components.blocks.divider', $data),
            BlockType::Spacer => view('components.blocks.spacer', $data),
            BlockType::Map => view('components.blocks.map', $data),
            BlockType::ContactForm => view('components.blocks.contact-form', $data),
        };
    }

    /**
     * Get hydrated block data for rendering
     */
    public function getBlockData(PageBlock $block): array
    {
        $content = is_array($block->content)
            ? $block->content
            : json_decode($block->content, true) ?? [];

        return $this->hydrator::hydrate($block->block_type, $content);
    }

    /**
     * Render all blocks from a collection
     */
    public function renderBlocks(iterable $blocks): string
    {
        $html = '';
        foreach ($blocks as $block) {
            if ($block->is_active) {
                $html .= $this->render($block) . "\n";
            }
        }
        return $html;
    }

    /**
     * Get component name from block type (for dynamic rendering)
     */
    public function getComponentName(BlockType $blockType): string
    {
        return match ($blockType) {
            BlockType::Hero => 'hero',
            BlockType::RichText => 'rich-text',
            BlockType::Image => 'image',
            BlockType::Gallery => 'gallery',
            BlockType::Video => 'video',
            BlockType::CTA => 'cta',
            BlockType::FAQ => 'faq',
            BlockType::Accordion => 'accordion',
            BlockType::Tabs => 'tabs',
            BlockType::Team => 'team',
            BlockType::Testimonials => 'testimonials',
            BlockType::Statistics => 'statistics',
            BlockType::Timeline => 'timeline',
            BlockType::Button => 'button',
            BlockType::Divider => 'divider',
            BlockType::Spacer => 'spacer',
            BlockType::Map => 'map',
            BlockType::ContactForm => 'contact-form',
        };
    }
}
