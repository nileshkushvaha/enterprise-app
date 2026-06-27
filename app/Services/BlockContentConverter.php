<?php

namespace App\Services;

use App\Enums\BlockType;

/**
 * Converts form data to JSON for storage in database.
 * Normalizes and validates the data structure for each block type.
 */
class BlockContentConverter
{
    public static function convert(BlockType $blockType, array $formData): array
    {
        return match ($blockType) {
            BlockType::Hero => self::heroToJson($formData),
            BlockType::RichText => self::richTextToJson($formData),
            BlockType::Image => self::imageToJson($formData),
            BlockType::Gallery => self::galleryToJson($formData),
            BlockType::Video => self::videoToJson($formData),
            BlockType::CTA => self::ctaToJson($formData),
            BlockType::FAQ => self::faqToJson($formData),
            BlockType::Accordion => self::accordionToJson($formData),
            BlockType::Tabs => self::tabsToJson($formData),
            BlockType::Team => self::teamToJson($formData),
            BlockType::Testimonials => self::testimonialsToJson($formData),
            BlockType::Statistics => self::statisticsToJson($formData),
            BlockType::Timeline => self::timelineToJson($formData),
            BlockType::Button => self::buttonToJson($formData),
            BlockType::Divider => self::dividerToJson($formData),
            BlockType::Spacer => self::spacerToJson($formData),
            BlockType::Map => self::mapToJson($formData),
            BlockType::ContactForm => self::contactFormToJson($formData),
        };
    }

    private static function heroToJson(array $data): array
    {
        return [
            'title' => $data['title'] ?? '',
            'subtitle' => $data['subtitle'] ?? '',
            'image' => $data['image'] ?? null,
            'button_text' => $data['button_text'] ?? '',
            'button_link' => $data['button_link'] ?? '',
            'button_style' => $data['button_style'] ?? 'primary',
        ];
    }

    private static function richTextToJson(array $data): array
    {
        return [
            'text' => $data['text'] ?? '',
        ];
    }

    private static function imageToJson(array $data): array
    {
        return [
            'image' => $data['image'] ?? null,
            'caption' => $data['caption'] ?? '',
            'alt_text' => $data['alt_text'] ?? '',
        ];
    }

    private static function galleryToJson(array $data): array
    {
        return [
            'images' => $data['images'] ?? [],
            'columns' => (int) ($data['columns'] ?? 3),
            'gap' => $data['gap'] ?? 'md',
        ];
    }

    private static function videoToJson(array $data): array
    {
        return [
            'video_url' => $data['video_url'] ?? '',
            'caption' => $data['caption'] ?? '',
            'thumbnail' => $data['thumbnail'] ?? null,
        ];
    }

    private static function ctaToJson(array $data): array
    {
        return [
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'button_text' => $data['button_text'] ?? '',
            'button_link' => $data['button_link'] ?? '',
            'button_style' => $data['button_style'] ?? 'primary',
            'background_color' => $data['background_color'] ?? '#ffffff',
            'text_color' => $data['text_color'] ?? '#000000',
        ];
    }

    private static function faqToJson(array $data): array
    {
        $items = $data['items'] ?? [];
        return [
            'items' => array_values(array_filter($items, fn ($item) => !empty($item['question'] ?? null))),
        ];
    }

    private static function accordionToJson(array $data): array
    {
        $items = $data['items'] ?? [];
        return [
            'items' => array_values(array_filter($items, fn ($item) => !empty($item['title'] ?? null))),
            'single_open' => (bool) ($data['single_open'] ?? true),
        ];
    }

    private static function tabsToJson(array $data): array
    {
        $items = $data['items'] ?? [];
        return [
            'items' => array_values(array_filter($items, fn ($item) => !empty($item['tab_title'] ?? null))),
        ];
    }

    private static function teamToJson(array $data): array
    {
        $members = $data['members'] ?? [];
        return [
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'members' => array_values(array_filter($members, fn ($member) => !empty($member['name'] ?? null))),
            'columns' => (int) ($data['columns'] ?? 3),
        ];
    }

    private static function testimonialsToJson(array $data): array
    {
        $testimonials = $data['testimonials'] ?? [];
        return [
            'testimonials' => array_values(array_filter($testimonials, fn ($t) => !empty($t['text'] ?? null))),
            'columns' => (int) ($data['columns'] ?? 3),
        ];
    }

    private static function statisticsToJson(array $data): array
    {
        $stats = $data['stats'] ?? [];
        return [
            'stats' => array_values(array_filter($stats, fn ($s) => !empty($s['number'] ?? null))),
            'columns' => (int) ($data['columns'] ?? 4),
        ];
    }

    private static function timelineToJson(array $data): array
    {
        $items = $data['items'] ?? [];
        return [
            'items' => array_values(array_filter($items, fn ($item) => !empty($item['title'] ?? null))),
        ];
    }

    private static function buttonToJson(array $data): array
    {
        return [
            'text' => $data['text'] ?? '',
            'link' => $data['link'] ?? '',
            'style' => $data['style'] ?? 'primary',
            'size' => $data['size'] ?? 'md',
            'alignment' => $data['alignment'] ?? 'left',
        ];
    }

    private static function dividerToJson(array $data): array
    {
        return [
            'style' => $data['style'] ?? 'solid',
            'color' => $data['color'] ?? '#e5e7eb',
            'width' => (int) ($data['width'] ?? 100),
        ];
    }

    private static function spacerToJson(array $data): array
    {
        return [
            'height' => (int) ($data['height'] ?? 60),
        ];
    }

    private static function mapToJson(array $data): array
    {
        return [
            'latitude' => $data['latitude'] ?? '0',
            'longitude' => $data['longitude'] ?? '0',
            'address' => $data['address'] ?? '',
            'title' => $data['title'] ?? '',
            'zoom' => (int) ($data['zoom'] ?? 15),
        ];
    }

    private static function contactFormToJson(array $data): array
    {
        $fields = $data['fields'] ?? [];
        return [
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'fields' => array_values(array_filter($fields, fn ($field) => !empty($field['label'] ?? null))),
            'button_text' => $data['button_text'] ?? 'Send Message',
            'success_message' => $data['success_message'] ?? 'Thank you for your message!',
        ];
    }
}
