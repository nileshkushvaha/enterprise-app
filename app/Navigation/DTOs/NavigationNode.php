<?php

declare(strict_types=1);

namespace App\Navigation\DTOs;

use App\Enums\Navigation\NavigationVisibility;

readonly class NavigationNode
{
    /**
     * @param  int[]  $requiredRoleIds
     * @param  int[]  $requiredPermissionIds
     * @param  list<NavigationNode>  $children
     */
    public function __construct(
        public string $id,
        public string $navigationId,
        public string $label,
        public ResolvedLink $link,
        public NavigationVisibility $visibility,
        public PublishWindow $publishWindow,
        public array $requiredRoleIds,
        public array $requiredPermissionIds,
        public ?string $icon,
        public ?string $cssClass,
        public ?string $cssId,
        public ?string $badgeText,
        public ?string $badgeColor,
        public bool $isActive,
        public bool $isAncestorActive,
        public int $depth,
        public int $sortOrder,
        public array $children,
        public ?string $locale = null,
        public bool $openInModal = false,
    ) {}

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    public function isLeaf(): bool
    {
        return $this->children === [];
    }

    public function withActive(bool $isActive, bool $isAncestorActive): self
    {
        return new self(
            id: $this->id,
            navigationId: $this->navigationId,
            label: $this->label,
            link: $this->link,
            visibility: $this->visibility,
            publishWindow: $this->publishWindow,
            requiredRoleIds: $this->requiredRoleIds,
            requiredPermissionIds: $this->requiredPermissionIds,
            icon: $this->icon,
            cssClass: $this->cssClass,
            cssId: $this->cssId,
            badgeText: $this->badgeText,
            badgeColor: $this->badgeColor,
            isActive: $isActive,
            isAncestorActive: $isAncestorActive,
            depth: $this->depth,
            sortOrder: $this->sortOrder,
            children: $this->children,
            locale: $this->locale,
            openInModal: $this->openInModal,
        );
    }

    public function withChildren(array $children): self
    {
        return new self(
            id: $this->id,
            navigationId: $this->navigationId,
            label: $this->label,
            link: $this->link,
            visibility: $this->visibility,
            publishWindow: $this->publishWindow,
            requiredRoleIds: $this->requiredRoleIds,
            requiredPermissionIds: $this->requiredPermissionIds,
            icon: $this->icon,
            cssClass: $this->cssClass,
            cssId: $this->cssId,
            badgeText: $this->badgeText,
            badgeColor: $this->badgeColor,
            isActive: $this->isActive,
            isAncestorActive: $this->isAncestorActive,
            depth: $this->depth,
            sortOrder: $this->sortOrder,
            children: $children,
            locale: $this->locale,
            openInModal: $this->openInModal,
        );
    }
}
