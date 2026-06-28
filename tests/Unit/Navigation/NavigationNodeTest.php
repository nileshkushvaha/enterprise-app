<?php

declare(strict_types=1);

namespace Tests\Unit\Navigation;

use App\Enums\Navigation\NavigationVisibility;
use App\Navigation\DTOs\NavigationNode;
use App\Navigation\DTOs\PublishWindow;
use App\Navigation\DTOs\ResolvedLink;
use Tests\TestCase;

class NavigationNodeTest extends TestCase
{
    private function makeNode(array $overrides = []): NavigationNode
    {
        return new NavigationNode(
            id: $overrides['id'] ?? 'abc-123',
            navigationId: $overrides['navigationId'] ?? 'nav-1',
            label: $overrides['label'] ?? 'Home',
            link: $overrides['link'] ?? new ResolvedLink('/', '_self', null, []),
            visibility: $overrides['visibility'] ?? NavigationVisibility::All,
            publishWindow: $overrides['publishWindow'] ?? PublishWindow::always(),
            requiredRoleIds: $overrides['requiredRoleIds'] ?? [],
            requiredPermissionIds: $overrides['requiredPermissionIds'] ?? [],
            icon: $overrides['icon'] ?? null,
            cssClass: $overrides['cssClass'] ?? null,
            cssId: $overrides['cssId'] ?? null,
            badgeText: $overrides['badgeText'] ?? null,
            badgeColor: $overrides['badgeColor'] ?? null,
            isActive: $overrides['isActive'] ?? false,
            isAncestorActive: $overrides['isAncestorActive'] ?? false,
            depth: $overrides['depth'] ?? 0,
            sortOrder: $overrides['sortOrder'] ?? 0,
            children: $overrides['children'] ?? [],
        );
    }

    public function test_has_children_returns_true_when_children_present(): void
    {
        $child  = $this->makeNode(['id' => 'child-1']);
        $parent = $this->makeNode(['children' => [$child]]);

        $this->assertTrue($parent->hasChildren());
    }

    public function test_has_children_returns_false_when_no_children(): void
    {
        $node = $this->makeNode();

        $this->assertFalse($node->hasChildren());
    }

    public function test_is_leaf_inverse_of_has_children(): void
    {
        $child  = $this->makeNode(['id' => 'child-1']);
        $parent = $this->makeNode(['children' => [$child]]);
        $leaf   = $this->makeNode();

        $this->assertFalse($parent->isLeaf());
        $this->assertTrue($leaf->isLeaf());
    }

    public function test_with_active_returns_new_instance(): void
    {
        $original = $this->makeNode(['isActive' => false]);
        $active   = $original->withActive(true, false);

        $this->assertNotSame($original, $active);
        $this->assertFalse($original->isActive);
        $this->assertTrue($active->isActive);
    }

    public function test_with_active_preserves_all_other_properties(): void
    {
        $original = $this->makeNode([
            'id'       => 'node-x',
            'label'    => 'About',
            'cssClass' => 'nav-item',
            'depth'    => 2,
        ]);

        $modified = $original->withActive(true, true);

        $this->assertSame('node-x', $modified->id);
        $this->assertSame('About', $modified->label);
        $this->assertSame('nav-item', $modified->cssClass);
        $this->assertSame(2, $modified->depth);
        $this->assertTrue($modified->isActive);
        $this->assertTrue($modified->isAncestorActive);
    }

    public function test_with_children_returns_new_instance(): void
    {
        $original = $this->makeNode();
        $child    = $this->makeNode(['id' => 'c1']);
        $updated  = $original->withChildren([$child]);

        $this->assertNotSame($original, $updated);
        $this->assertCount(0, $original->children);
        $this->assertCount(1, $updated->children);
    }

    public function test_with_children_preserves_other_properties(): void
    {
        $original = $this->makeNode(['label' => 'Services', 'depth' => 1]);
        $updated  = $original->withChildren([$this->makeNode(['id' => 'sub'])]);

        $this->assertSame('Services', $updated->label);
        $this->assertSame(1, $updated->depth);
    }

    public function test_properties_are_readonly(): void
    {
        $node = $this->makeNode();
        $ref  = new \ReflectionProperty($node, 'id');

        $this->assertTrue($ref->isReadOnly());
    }
}
