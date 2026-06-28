<?php

declare(strict_types=1);

namespace App\Navigation\Services;

use App\Models\NavigationItem;
use App\Navigation\DTOs\ResolvedLink;
use App\Navigation\Registry\LinkTypeRegistry;

final class UrlResolver
{
    public function __construct(
        private readonly LinkTypeRegistry $registry,
    ) {}

    public function resolve(NavigationItem $item): ResolvedLink
    {
        if (! $this->registry->has($item->link_type)) {
            return $this->fallback($item);
        }

        return $this->registry->resolve($item->link_type)->resolve($item);
    }

    private function fallback(NavigationItem $item): ResolvedLink
    {
        return new ResolvedLink(
            url: $item->url ?? '#',
            target: $item->target,
            rel: $item->rel,
            attributes: [],
        );
    }
}
