<?php

namespace App\Actions;

use App\Enums\BlockType;

class ValidateBlockContentAction
{
    /**
     * Validate block content based on block type
     */
    public function execute(BlockType $blockType, array $content): array
    {
        $errors = [];

        return match ($blockType) {
            BlockType::Hero => $this->validateHero($content, $errors),
            BlockType::RichText => $this->validateRichText($content, $errors),
            BlockType::Image => $this->validateImage($content, $errors),
            BlockType::Gallery => $this->validateGallery($content, $errors),
            BlockType::Video => $this->validateVideo($content, $errors),
            BlockType::CTA => $this->validateCTA($content, $errors),
            BlockType::FAQ => $this->validateFAQ($content, $errors),
            BlockType::Accordion => $this->validateAccordion($content, $errors),
            BlockType::Tabs => $this->validateTabs($content, $errors),
            BlockType::Team => $this->validateTeam($content, $errors),
            BlockType::Testimonials => $this->validateTestimonials($content, $errors),
            BlockType::Statistics => $this->validateStatistics($content, $errors),
            BlockType::Timeline => $this->validateTimeline($content, $errors),
            BlockType::Button => $this->validateButton($content, $errors),
            BlockType::Divider => $this->validateDivider($content, $errors),
            BlockType::Spacer => $this->validateSpacer($content, $errors),
            BlockType::Map => $this->validateMap($content, $errors),
            BlockType::ContactForm => $this->validateContactForm($content, $errors),
        };
    }

    private function validateHero(array $content, array $errors): array
    {
        if (empty($content['title'])) {
            $errors[] = 'Hero block requires a title';
        }
        return $errors;
    }

    private function validateRichText(array $content, array $errors): array
    {
        if (empty($content['text'])) {
            $errors[] = 'Rich text block requires content';
        }
        return $errors;
    }

    private function validateImage(array $content, array $errors): array
    {
        if (empty($content['image'])) {
            $errors[] = 'Image block requires an image';
        }
        return $errors;
    }

    private function validateGallery(array $content, array $errors): array
    {
        if (empty($content['images']) || !is_array($content['images'])) {
            $errors[] = 'Gallery block requires at least one image';
        }
        return $errors;
    }

    private function validateVideo(array $content, array $errors): array
    {
        if (empty($content['video_url'])) {
            $errors[] = 'Video block requires a URL';
        }
        return $errors;
    }

    private function validateCTA(array $content, array $errors): array
    {
        if (empty($content['title'])) {
            $errors[] = 'CTA block requires a title';
        }
        if (empty($content['button_link'])) {
            $errors[] = 'CTA block requires a button link';
        }
        return $errors;
    }

    private function validateFAQ(array $content, array $errors): array
    {
        if (empty($content['items']) || !is_array($content['items'])) {
            $errors[] = 'FAQ block requires at least one item';
        }
        return $errors;
    }

    private function validateAccordion(array $content, array $errors): array
    {
        if (empty($content['items']) || !is_array($content['items'])) {
            $errors[] = 'Accordion block requires at least one item';
        }
        return $errors;
    }

    private function validateTabs(array $content, array $errors): array
    {
        if (empty($content['items']) || !is_array($content['items'])) {
            $errors[] = 'Tabs block requires at least one tab';
        }
        return $errors;
    }

    private function validateTeam(array $content, array $errors): array
    {
        if (empty($content['members']) || !is_array($content['members'])) {
            $errors[] = 'Team block requires at least one member';
        }
        return $errors;
    }

    private function validateTestimonials(array $content, array $errors): array
    {
        if (empty($content['testimonials']) || !is_array($content['testimonials'])) {
            $errors[] = 'Testimonials block requires at least one testimonial';
        }
        return $errors;
    }

    private function validateStatistics(array $content, array $errors): array
    {
        if (empty($content['stats']) || !is_array($content['stats'])) {
            $errors[] = 'Statistics block requires at least one statistic';
        }
        return $errors;
    }

    private function validateTimeline(array $content, array $errors): array
    {
        if (empty($content['items']) || !is_array($content['items'])) {
            $errors[] = 'Timeline block requires at least one item';
        }
        return $errors;
    }

    private function validateButton(array $content, array $errors): array
    {
        if (empty($content['text'])) {
            $errors[] = 'Button block requires text';
        }
        if (empty($content['link'])) {
            $errors[] = 'Button block requires a link';
        }
        return $errors;
    }

    private function validateDivider(array $content, array $errors): array
    {
        // Divider has minimal requirements
        return $errors;
    }

    private function validateSpacer(array $content, array $errors): array
    {
        if (empty($content['height']) || !is_numeric($content['height'])) {
            $errors[] = 'Spacer block requires a height value';
        }
        return $errors;
    }

    private function validateMap(array $content, array $errors): array
    {
        if (!isset($content['latitude']) || !is_numeric($content['latitude'])) {
            $errors[] = 'Map block requires latitude';
        }
        if (!isset($content['longitude']) || !is_numeric($content['longitude'])) {
            $errors[] = 'Map block requires longitude';
        }
        return $errors;
    }

    private function validateContactForm(array $content, array $errors): array
    {
        if (empty($content['fields']) || !is_array($content['fields'])) {
            $errors[] = 'Contact form requires at least one field';
        }
        return $errors;
    }
}
