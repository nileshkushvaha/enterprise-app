<?php

declare(strict_types=1);

namespace App\Navigation\Drivers;

use App\Enums\Navigation\NavigationLinkType;
use App\Models\NavigationItem;
use App\Navigation\Contracts\LinkTypeDriverInterface;
use App\Navigation\DTOs\ResolvedLink;

final class ExternalLinkDriver implements LinkTypeDriverInterface
{
    public function resolve(NavigationItem $item): ResolvedLink
    {
        $rel = $item->rel ?? 'noopener noreferrer';

        return new ResolvedLink(
            url: $item->url ?? '#',
            target: '_blank',
            rel: $rel,
            attributes: [],
        );
    }

    public function supports(NavigationLinkType $type): bool
    {
        return $type === NavigationLinkType::External;
    }
}
