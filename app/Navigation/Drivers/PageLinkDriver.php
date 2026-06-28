<?php

declare(strict_types=1);

namespace App\Navigation\Drivers;

use App\Enums\Navigation\NavigationLinkType;
use App\Models\NavigationItem;
use App\Navigation\Contracts\LinkTypeDriverInterface;
use App\Navigation\DTOs\ResolvedLink;
use Illuminate\Contracts\Routing\UrlGenerator;

final class PageLinkDriver implements LinkTypeDriverInterface
{
    public function __construct(
        private readonly UrlGenerator $url,
    ) {}

    public function resolve(NavigationItem $item): ResolvedLink
    {
        $linkable = $item->linkable;

        $url = $linkable !== null
            ? $this->url->route('page.show', ['slug' => $linkable->slug])
            : '#';

        return new ResolvedLink(
            url: $url,
            target: $item->target,
            rel: $item->rel,
            attributes: [],
        );
    }

    public function supports(NavigationLinkType $type): bool
    {
        return $type === NavigationLinkType::Page;
    }
}
