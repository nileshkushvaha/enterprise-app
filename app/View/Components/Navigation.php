<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Enums\Navigation\NavigationLayoutType;
use App\Enums\Navigation\NavigationLocation;
use App\Navigation\DTOs\NavigationTree;
use App\Navigation\Services\NavigationManager;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Navigation extends Component
{
    public readonly ?NavigationTree $tree;

    public readonly string $navLabel;

    public function __construct(
        NavigationManager $manager,
        public readonly string $location,
        public readonly ?string $locale = null,
        public readonly ?string $label = null,
    ) {
        $this->tree = $this->resolveTree($manager);
        $this->navLabel = $label ?? ($this->tree?->name ?? $location);
    }

    public function render(): View
    {
        if ($this->tree === null || $this->tree->isEmpty()) {
            return view('components.navigation.empty');
        }

        return view($this->viewForTree($this->tree));
    }

    private function resolveTree(NavigationManager $manager): ?NavigationTree
    {
        $locationEnum = NavigationLocation::tryFrom($this->location);

        return $locationEnum !== null
            ? $manager->forLocation($locationEnum, $this->locale)
            : $manager->forSlug($this->location, $this->locale);
    }

    private function viewForTree(NavigationTree $tree): string
    {
        // Accordion / flyout always render as sidebar-style
        if (in_array($tree->layoutType, [NavigationLayoutType::Accordion, NavigationLayoutType::Flyout], true)) {
            return 'components.navigation.sidebar';
        }

        return match ($tree->location) {
            NavigationLocation::Footer => 'components.navigation.footer',
            NavigationLocation::Mobile => 'components.navigation.mobile',
            NavigationLocation::Sidebar => 'components.navigation.sidebar',
            default => 'components.navigation.standard',
        };
    }
}
