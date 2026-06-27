<?php

namespace App\Enums;

enum BlockType: string
{
    case Hero = 'hero';
    case RichText = 'rich_text';
    case Image = 'image';
    case Gallery = 'gallery';
    case Video = 'video';
    case CTA = 'cta';
    case FAQ = 'faq';
    case Accordion = 'accordion';
    case Tabs = 'tabs';
    case Team = 'team';
    case Testimonials = 'testimonials';
    case Statistics = 'statistics';
    case Timeline = 'timeline';
    case Button = 'button';
    case Divider = 'divider';
    case Spacer = 'spacer';
    case Map = 'map';
    case ContactForm = 'contact_form';

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Hero',
            self::RichText => 'Rich Text',
            self::Image => 'Image',
            self::Gallery => 'Gallery',
            self::Video => 'Video',
            self::CTA => 'Call to Action',
            self::FAQ => 'FAQ',
            self::Accordion => 'Accordion',
            self::Tabs => 'Tabs',
            self::Team => 'Team',
            self::Testimonials => 'Testimonials',
            self::Statistics => 'Statistics',
            self::Timeline => 'Timeline',
            self::Button => 'Button',
            self::Divider => 'Divider',
            self::Spacer => 'Spacer',
            self::Map => 'Map',
            self::ContactForm => 'Contact Form',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Hero => 'heroicon-m-photo',
            self::RichText => 'heroicon-m-document-text',
            self::Image => 'heroicon-m-image',
            self::Gallery => 'heroicon-m-photo-square',
            self::Video => 'heroicon-m-video-camera',
            self::CTA => 'heroicon-m-megaphone',
            self::FAQ => 'heroicon-m-question-mark-circle',
            self::Accordion => 'heroicon-m-list-bullet',
            self::Tabs => 'heroicon-m-rectangle-group',
            self::Team => 'heroicon-m-user-group',
            self::Testimonials => 'heroicon-m-star',
            self::Statistics => 'heroicon-m-chart-bar',
            self::Timeline => 'heroicon-m-arrow-long-down',
            self::Button => 'heroicon-m-hand-raised',
            self::Divider => 'heroicon-m-minus',
            self::Spacer => 'heroicon-m-pause',
            self::Map => 'heroicon-m-map',
            self::ContactForm => 'heroicon-m-envelope',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Hero => 'Large banner with title, subtitle, image, and CTA button',
            self::RichText => 'Rich text content with formatting',
            self::Image => 'Single image with caption',
            self::Gallery => 'Multiple images in a grid',
            self::Video => 'Embedded video player',
            self::CTA => 'Call-to-action section with button',
            self::FAQ => 'Frequently asked questions',
            self::Accordion => 'Expandable accordion sections',
            self::Tabs => 'Tabbed content sections',
            self::Team => 'Team member profiles',
            self::Testimonials => 'Customer testimonials/reviews',
            self::Statistics => 'Statistics/metrics display',
            self::Timeline => 'Timeline of events',
            self::Button => 'Single button element',
            self::Divider => 'Visual divider/separator',
            self::Spacer => 'Whitespace/spacing element',
            self::Map => 'Map/location display',
            self::ContactForm => 'Contact form',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::Hero, self::Divider, self::Spacer => 'Layout',
            self::RichText, self::Button => 'Content',
            self::Image, self::Gallery, self::Video => 'Media',
            self::CTA => 'Call to Action',
            self::FAQ, self::Accordion, self::Tabs => 'Interactive',
            self::Team, self::Testimonials, self::Statistics, self::Timeline => 'Components',
            self::Map => 'Location',
            self::ContactForm => 'Forms',
        };
    }

    /**
     * Get Filament badge color for block type
     */
    public function color(): string
    {
        return match ($this) {
            self::Hero => 'info',
            self::RichText => 'success',
            self::Image, self::Gallery, self::Video => 'warning',
            self::CTA => 'danger',
            self::FAQ, self::Accordion, self::Tabs => 'purple',
            self::Team, self::Testimonials, self::Statistics, self::Timeline => 'blue',
            self::Button => 'pink',
            self::Divider, self::Spacer => 'gray',
            self::Map => 'cyan',
            self::ContactForm => 'amber',
        };
    }

    /**
     * Get all block types grouped by category
     */
    public static function grouped(): array
    {
        $grouped = [];
        
        foreach (self::cases() as $type) {
            $category = $type->category();
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $type;
        }
        
        return $grouped;
    }
}
