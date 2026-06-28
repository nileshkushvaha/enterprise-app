<?php

declare(strict_types=1);

namespace Tests\Unit\Navigation;

use App\Enums\Navigation\NavigationLayoutType;
use App\Enums\Navigation\NavigationLocation;
use App\Enums\Navigation\NavigationVisibility;
use App\Navigation\DTOs\NavigationNode;
use App\Navigation\DTOs\NavigationTree;
use App\Navigation\DTOs\PublishWindow;
use App\Navigation\DTOs\ResolvedLink;
use Tests\TestCase;

class NavigationTreeTest extends TestCase
{
    private function makeTree(array $nodes = []): NavigationTree
    {
        return new NavigationTree(
            id: 'tree-1',
            name: 'Header',
            slug: 'header',
            location: NavigationLocation::Header,
            layoutType: NavigationLayoutType::Standard,
            totalNodes: count($nodes),
            nodes: $nodes,
        );
    }

    private function makeNode(bool $isActive = false, bool $isAncestorActive = false, array $children = []): NavigationNode
    {
        return new NavigationNode(
            id: 'n-'.uniqid(),
            navigationId: 'tree-1',
            label: 'Item',
            link: new ResolvedLink('/', '_self', null, []),
            visibility: NavigationVisibility::All,
            publishWindow: PublishWindow::always(),
            requiredRoleIds: [],
            requiredPermissionIds: [],
            icon: null,
            cssClass: null,
            cssId: null,
            badgeText: null,
            badgeColor: null,
            isActive: $isActive,
            isAncestorActive: $isAncestorActive,
            depth: 0,
            sortOrder: 0,
            children: $children,
        );
    }

    public function test_is_empty_returns_true_with_no_nodes(): void
    {
        $this->assertTrue($this->makeTree()->isEmpty());
    }

    public function test_is_empty_returns_false_with_nodes(): void
    {
        $tree = $this->makeTree([$this->makeNode()]);

        $this->assertFalse($tree->isEmpty());
    }

    public function test_has_active_node_returns_false_when_no_active(): void
    {
        $tree = $this->makeTree([$this->makeNode(false, false)]);

        $this->assertFalse($tree->hasActiveNode());
    }

    public function test_has_active_node_returns_true_when_node_is_active(): void
    {
        $tree = $this->makeTree([$this->makeNode(true, false)]);

        $this->assertTrue($tree->hasActiveNode());
    }

    public function test_has_active_node_detects_ancestor_active(): void
    {
        $tree = $this->makeTree([$this->makeNode(false, true)]);

        $this->assertTrue($tree->hasActiveNode());
    }

    public function test_has_active_node_recurses_into_children(): void
    {
        $activeChild = $this->makeNode(true);
        $parent = $this->makeNode(false, false, [$activeChild]);
        $tree = $this->makeTree([$parent]);

        $this->assertTrue($tree->hasActiveNode());
    }

    public function test_with_nodes_returns_new_tree_instance(): void
    {
        $original = $this->makeTree();
        $updated = $original->withNodes([$this->makeNode()]);

        $this->assertNotSame($original, $updated);
        $this->assertCount(0, $original->nodes);
        $this->assertCount(1, $updated->nodes);
    }

    public function test_with_nodes_updates_total_nodes_count(): void
    {
        $tree = $this->makeTree();
        $updated = $tree->withNodes([$this->makeNode(), $this->makeNode()]);

        $this->assertSame(2, $updated->totalNodes);
    }

    public function test_with_nodes_preserves_metadata(): void
    {
        $tree = $this->makeTree();
        $updated = $tree->withNodes([]);

        $this->assertSame('tree-1', $updated->id);
        $this->assertSame('Header', $updated->name);
        $this->assertSame('header', $updated->slug);
        $this->assertSame(NavigationLocation::Header, $updated->location);
    }
}
