<?php

declare(strict_types=1);

namespace App\Navigation\Drivers;

use App\Enums\Navigation\NavigationLinkType;
use App\Models\NavigationItem;
use App\Navigation\Contracts\LinkTypeDriverInterface;
use App\Navigation\DTOs\ResolvedLink;
use Illuminate\Contracts\Routing\UrlGenerator;
use Throwable;

final class RouteLinkDriver implements LinkTypeDriverInterface
{
    public function __construct(
        private readonly UrlGenerator $url,
    ) {}

    public function resolve(NavigationItem $item): ResolvedLink
    {
        $resolved = '#';

        if (filled($item->route_name)) {
            try {
                $resolved = $this->url->route(
                    $item->route_name,
                    $item->route_params ?? [],
                );
            } catch (Throwable) {
                $resolved = '#';
            }
        }

        return new ResolvedLink(
            url: $resolved,
            target: $item->target,
            rel: $item->rel,
            attributes: [],
        );
    }

    public function supports(NavigationLinkType $type): bool
    {
        return $type === NavigationLinkType::Route;
    }
}
