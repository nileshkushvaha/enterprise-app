<?php

declare(strict_types=1);

namespace App\Navigation\DTOs;

use App\Enums\Navigation\NavigationLayoutType;
use App\Enums\Navigation\NavigationLocation;

readonly class NavigationTree
{
    /** @param list<NavigationNode> $nodes */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public NavigationLocation $location,
        public NavigationLayoutType $layoutType,
        public int $totalNodes,
        public array $nodes,
    ) {}

    public function isEmpty(): bool
    {
        return $this->nodes === [];
    }

    public function hasActiveNode(): bool
    {
        return $this->findActive($this->nodes);
    }

    public function withNodes(array $nodes): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            slug: $this->slug,
            location: $this->location,
            layoutType: $this->layoutType,
            totalNodes: count($nodes),
            nodes: $nodes,
        );
    }

    /** @param list<NavigationNode> $nodes */
    private function findActive(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node->isActive || $node->isAncestorActive) {
                return true;
            }

            if ($node->hasChildren() && $this->findActive($node->children)) {
                return true;
            }
        }

        return false;
    }
}
