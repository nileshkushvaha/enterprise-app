<?php

declare(strict_types=1);

namespace App\Navigation\DTOs;

use App\Enums\Navigation\NavigationLinkType;
use App\Enums\Navigation\NavigationVisibility;
use Carbon\Carbon;
use Carbon\CarbonInterface;

readonly class NavigationItemData
{
    /**
     * @param  int[]  $requiredRoleIds
     * @param  int[]  $requiredPermissionIds
     * @param  array<string, mixed>  $extraAttributes
     */
    public function __construct(
        public string $label,
        public NavigationLinkType $linkType,
        public ?string $url = null,
        public ?string $routeName = null,
        public array $routeParams = [],
        public ?string $linkableType = null,
        public ?string $linkableId = null,
        public string $target = '_self',
        public ?string $rel = null,
        public ?string $icon = null,
        public ?string $cssClass = null,
        public ?string $cssId = null,
        public ?string $badgeText = null,
        public ?string $badgeColor = null,
        public NavigationVisibility $visibility = NavigationVisibility::All,
        public array $requiredRoleIds = [],
        public array $requiredPermissionIds = [],
        public bool $isActive = true,
        public bool $openInModal = false,
        public ?string $parentId = null,
        public array $extraAttributes = [],
        public ?string $locale = null,
        public ?CarbonInterface $publishFrom = null,
        public ?CarbonInterface $publishUntil = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            label: $data['label'],
            linkType: NavigationLinkType::from($data['link_type'] ?? 'url'),
            url: $data['url'] ?? null,
            routeName: $data['route_name'] ?? null,
            routeParams: (array) ($data['route_params'] ?? []),
            linkableType: $data['linkable_type'] ?? null,
            linkableId: $data['linkable_id'] ?? null,
            target: $data['target'] ?? '_self',
            rel: $data['rel'] ?? null,
            icon: $data['icon'] ?? null,
            cssClass: $data['css_class'] ?? null,
            cssId: $data['css_id'] ?? null,
            badgeText: $data['badge_text'] ?? null,
            badgeColor: $data['badge_color'] ?? null,
            visibility: NavigationVisibility::from($data['visibility'] ?? 'all'),
            requiredRoleIds: array_map('intval', (array) ($data['required_role_ids'] ?? [])),
            requiredPermissionIds: array_map('intval', (array) ($data['required_permission_ids'] ?? [])),
            isActive: (bool) ($data['is_active'] ?? true),
            openInModal: (bool) ($data['open_in_modal'] ?? false),
            parentId: $data['parent_id'] ?? null,
            extraAttributes: (array) ($data['extra_attributes'] ?? []),
            locale: ($data['locale'] ?? '') !== '' ? (string) $data['locale'] : null,
            publishFrom: self::parseDate($data['publish_from'] ?? null),
            publishUntil: self::parseDate($data['publish_until'] ?? null),
        );
    }

    private static function parseDate(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
