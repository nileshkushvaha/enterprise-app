<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Enums\Navigation\NavigationLayoutType;
use App\Enums\Navigation\NavigationLocation;
use App\Enums\Navigation\NavigationVisibility;
use App\Navigation\DTOs\NavigationNode;
use App\Navigation\DTOs\NavigationTree;
use App\Navigation\DTOs\PublishWindow;
use App\Navigation\DTOs\ResolvedLink;
use App\Navigation\Services\ActiveDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ActiveDetectorTest extends TestCase
{
    use RefreshDatabase;

    private function makeDetector(string $url): ActiveDetector
    {
        $request = Request::create($url);
        $this->app->instance(Request::class, $request);
        $this->app->instance('request', $request);

        return app(ActiveDetector::class);
    }

    private function makeTree(array $nodes): NavigationTree
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

    private function makeNode(string $url, array $children = []): NavigationNode
    {
        return new NavigationNode(
            id: 'n-' . sha1($url),
            navigationId: 'tree-1',
            label: 'Item',
            link: new ResolvedLink($url, '_self', null, []),
            visibility: NavigationVisibility::All,
            publishWindow: PublishWindow::always(),
            requiredRoleIds: [],
            requiredPermissionIds: [],
            icon: null,
            cssClass: null,
            cssId: null,
            badgeText: null,
            badgeColor: null,
            isActive: false,
            isAncestorActive: false,
            depth: 0,
            sortOrder: 0,
            children: $children,
        );
    }

    // ── Exact URL matching ────────────────────────────────────────────────

    public function test_exact_url_match_marks_node_active(): void
    {
        $detector = $this->makeDetector('http://localhost/about');
        $tree     = $this->makeTree([$this->makeNode('http://localhost/about')]);

        $result = $detector->markActive($tree);

        $this->assertTrue($result->nodes[0]->isActive);
    }

    public function test_non_matching_url_keeps_node_inactive(): void
    {
        $detector = $this->makeDetector('http://localhost/contact');
        $tree     = $this->makeTree([$this->makeNode('http://localhost/about')]);

        $result = $detector->markActive($tree);

        $this->assertFalse($result->nodes[0]->isActive);
    }

    public function test_hash_url_never_matches(): void
    {
        $detector = $this->makeDetector('http://localhost/');
        $tree     = $this->makeTree([$this->makeNode('#')]);

        $result = $detector->markActive($tree);

        $this->assertFalse($result->nodes[0]->isActive);
    }

    public function test_mailto_url_never_matches(): void
    {
        $detector = $this->makeDetector('http://localhost/');
        $tree     = $this->makeTree([$this->makeNode('mailto:a@b.com')]);

        $result = $detector->markActive($tree);

        $this->assertFalse($result->nodes[0]->isActive);
    }

    public function test_tel_url_never_matches(): void
    {
        $detector = $this->makeDetector('http://localhost/');
        $tree     = $this->makeTree([$this->makeNode('tel:+1234')]);

        $result = $detector->markActive($tree);

        $this->assertFalse($result->nodes[0]->isActive);
    }

    // ── Trailing slash normalisation ──────────────────────────────────────

    public function test_trailing_slash_is_normalised(): void
    {
        $detector = $this->makeDetector('http://localhost/about/');
        $tree     = $this->makeTree([$this->makeNode('http://localhost/about')]);

        $result = $detector->markActive($tree);

        $this->assertTrue($result->nodes[0]->isActive);
    }

    // ── Ancestor propagation ──────────────────────────────────────────────

    public function test_parent_is_marked_ancestor_active_when_child_is_active(): void
    {
        $detector = $this->makeDetector('http://localhost/services/web');

        $child  = $this->makeNode('http://localhost/services/web');
        $parent = $this->makeNode('http://localhost/services', [$child]);
        $tree   = $this->makeTree([$parent]);

        $result = $detector->markActive($tree);

        $this->assertTrue($result->nodes[0]->isAncestorActive);
        $this->assertTrue($result->nodes[0]->children[0]->isActive);
    }

    public function test_parent_is_not_ancestor_active_when_no_child_active(): void
    {
        $detector = $this->makeDetector('http://localhost/contact');

        $child  = $this->makeNode('http://localhost/services/web');
        $parent = $this->makeNode('http://localhost/services', [$child]);
        $tree   = $this->makeTree([$parent]);

        $result = $detector->markActive($tree);

        $this->assertFalse($result->nodes[0]->isAncestorActive);
    }

    // ── Immutability ──────────────────────────────────────────────────────

    public function test_mark_active_returns_new_tree_instance(): void
    {
        $detector = $this->makeDetector('http://localhost/');
        $tree     = $this->makeTree([]);

        $result = $detector->markActive($tree);

        $this->assertNotSame($tree, $result);
    }

    public function test_original_tree_nodes_are_not_mutated(): void
    {
        $detector = $this->makeDetector('http://localhost/about');
        $node     = $this->makeNode('http://localhost/about');
        $tree     = $this->makeTree([$node]);

        $detector->markActive($tree);

        // Original node still not active
        $this->assertFalse($node->isActive);
    }
}
