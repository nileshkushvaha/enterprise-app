<?php

declare(strict_types=1);

namespace App\Navigation\Services;

use App\Navigation\DTOs\NavigationNode;
use App\Navigation\DTOs\NavigationTree;
use Illuminate\Http\Request;

final class ActiveDetector
{
    public function __construct(
        private readonly Request $request,
    ) {}

    public function markActive(NavigationTree $tree): NavigationTree
    {
        $nodes = $this->processNodes($tree->nodes);

        return $tree->withNodes($nodes);
    }

    /** @param list<NavigationNode> $nodes */
    private function processNodes(array $nodes): array
    {
        return array_map(function (NavigationNode $node): NavigationNode {
            $children = $node->hasChildren()
                ? $this->processNodes($node->children)
                : [];

            $childHasActive = $this->anyActive($children);
            $isActive = $this->isNodeActive($node);

            return $node
                ->withChildren($children)
                ->withActive(
                    isActive: $isActive,
                    isAncestorActive: $childHasActive,
                );
        }, $nodes);
    }

    private function isNodeActive(NavigationNode $node): bool
    {
        $nodeUrl = $node->link->url;

        if ($nodeUrl === '#' || $nodeUrl === '') {
            return false;
        }

        $currentUrl = $this->request->url();
        $currentRoute = $this->request->route()?->getName();

        if (str_starts_with($nodeUrl, '#') || str_starts_with($nodeUrl, 'mailto:') || str_starts_with($nodeUrl, 'tel:')) {
            return false;
        }

        // Exact URL match
        if (rtrim($currentUrl, '/') === rtrim($nodeUrl, '/')) {
            return true;
        }

        // Named route match encoded in URL
        if ($currentRoute !== null && $this->matchesRoute($nodeUrl, $currentRoute)) {
            return true;
        }

        return false;
    }

    private function matchesRoute(string $nodeUrl, string $currentRoute): bool
    {
        // Extract path from node URL to compare with current full URL
        $nodeUrlParsed = parse_url($nodeUrl, PHP_URL_PATH);
        $currentUrlParsed = parse_url($this->request->url(), PHP_URL_PATH);

        if ($nodeUrlParsed === null || $currentUrlParsed === null) {
            return false;
        }

        return rtrim($nodeUrlParsed, '/') === rtrim($currentUrlParsed, '/');
    }

    /** @param list<NavigationNode> $nodes */
    private function anyActive(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node->isActive || $node->isAncestorActive) {
                return true;
            }
        }

        return false;
    }
}
