<?php

declare(strict_types=1);

namespace App\Navigation\Drivers;

use App\Enums\Navigation\NavigationLinkType;
use App\Models\NavigationItem;
use App\Navigation\Contracts\LinkTypeDriverInterface;
use App\Navigation\DTOs\ResolvedLink;

final class UrlLinkDriver implements LinkTypeDriverInterface
{
    public function resolve(NavigationItem $item): ResolvedLink
    {
        return new ResolvedLink(
            url: $item->url ?? '#',
            target: $item->target,
            rel: $item->rel,
            attributes: [],
        );
    }

    public function supports(NavigationLinkType $type): bool
    {
        return $type === NavigationLinkType::Url;
    }
}
