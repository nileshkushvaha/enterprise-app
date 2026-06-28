<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Enums\Navigation\NavigationVisibility;
use App\Models\User;
use App\Navigation\DTOs\NavigationNode;
use App\Navigation\DTOs\PublishWindow;
use App\Navigation\DTOs\ResolvedLink;
use App\Navigation\Services\PermissionEvaluator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private PermissionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = app(PermissionEvaluator::class);
    }

    private function makeNode(array $overrides = []): NavigationNode
    {
        return new NavigationNode(
            id: 'node-1',
            navigationId: 'nav-1',
            label: 'Test',
            link: new ResolvedLink('/', '_self', null, []),
            visibility: $overrides['visibility'] ?? NavigationVisibility::All,
            publishWindow: $overrides['publishWindow'] ?? PublishWindow::always(),
            requiredRoleIds: $overrides['requiredRoleIds'] ?? [],
            requiredPermissionIds: $overrides['requiredPermissionIds'] ?? [],
            icon: null,
            cssClass: null,
            cssId: null,
            badgeText: null,
            badgeColor: null,
            isActive: false,
            isAncestorActive: false,
            depth: 0,
            sortOrder: 0,
            children: [],
        );
    }

    // ── All ───────────────────────────────────────────────────────────────

    public function test_all_visibility_is_visible_to_guests(): void
    {
        $node = $this->makeNode(['visibility' => NavigationVisibility::All]);

        $this->assertTrue($this->evaluator->isVisible($node, null));
    }

    public function test_all_visibility_is_visible_to_authenticated_users(): void
    {
        $node = $this->makeNode(['visibility' => NavigationVisibility::All]);
        $user = User::factory()->create();

        $this->assertTrue($this->evaluator->isVisible($node, $user));
    }

    // ── Guest ─────────────────────────────────────────────────────────────

    public function test_guest_visibility_visible_to_guests(): void
    {
        $node = $this->makeNode(['visibility' => NavigationVisibility::Guest]);

        $this->assertTrue($this->evaluator->isVisible($node, null));
    }

    public function test_guest_visibility_hidden_from_authenticated(): void
    {
        $node = $this->makeNode(['visibility' => NavigationVisibility::Guest]);
        $user = User::factory()->create();

        $this->assertFalse($this->evaluator->isVisible($node, $user));
    }

    // ── Auth ──────────────────────────────────────────────────────────────

    public function test_auth_visibility_hidden_from_guests(): void
    {
        $node = $this->makeNode(['visibility' => NavigationVisibility::Auth]);

        $this->assertFalse($this->evaluator->isVisible($node, null));
    }

    public function test_auth_visibility_visible_to_authenticated(): void
    {
        $node = $this->makeNode(['visibility' => NavigationVisibility::Auth]);
        $user = User::factory()->create();

        $this->assertTrue($this->evaluator->isVisible($node, $user));
    }

    // ── Roles ─────────────────────────────────────────────────────────────

    public function test_roles_visibility_hidden_from_guests(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $node = $this->makeNode([
            'visibility' => NavigationVisibility::Roles,
            'requiredRoleIds' => [(int) $role->id],
        ]);

        $this->assertFalse($this->evaluator->isVisible($node, null));
    }

    public function test_roles_visibility_hidden_from_user_without_role(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $node = $this->makeNode([
            'visibility' => NavigationVisibility::Roles,
            'requiredRoleIds' => [(int) $role->id],
        ]);

        $this->assertFalse($this->evaluator->isVisible($node, $user));
    }

    public function test_roles_visibility_visible_to_user_with_role(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $node = $this->makeNode([
            'visibility' => NavigationVisibility::Roles,
            'requiredRoleIds' => [(int) $role->id],
        ]);

        // Refresh permissions cache
        $user = $user->fresh();

        $this->assertTrue($this->evaluator->isVisible($node, $user));
    }

    public function test_roles_visibility_hidden_when_required_roles_empty(): void
    {
        $user = User::factory()->create();
        $node = $this->makeNode([
            'visibility' => NavigationVisibility::Roles,
            'requiredRoleIds' => [],
        ]);

        $this->assertFalse($this->evaluator->isVisible($node, $user));
    }

    // ── Permissions ───────────────────────────────────────────────────────

    public function test_permissions_visibility_hidden_from_guests(): void
    {
        $perm = Permission::create(['name' => 'view reports', 'guard_name' => 'web']);
        $node = $this->makeNode([
            'visibility' => NavigationVisibility::Permissions,
            'requiredPermissionIds' => [(int) $perm->id],
        ]);

        $this->assertFalse($this->evaluator->isVisible($node, null));
    }

    public function test_permissions_visibility_hidden_from_user_without_permission(): void
    {
        $perm = Permission::create(['name' => 'view reports', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $node = $this->makeNode([
            'visibility' => NavigationVisibility::Permissions,
            'requiredPermissionIds' => [(int) $perm->id],
        ]);

        $this->assertFalse($this->evaluator->isVisible($node, $user));
    }

    public function test_permissions_visibility_visible_with_direct_permission(): void
    {
        $perm = Permission::create(['name' => 'view reports', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo($perm);

        $node = $this->makeNode([
            'visibility' => NavigationVisibility::Permissions,
            'requiredPermissionIds' => [(int) $perm->id],
        ]);

        $user = $user->fresh();

        $this->assertTrue($this->evaluator->isVisible($node, $user));
    }

    public function test_permissions_visibility_visible_with_role_permission(): void
    {
        $perm = Permission::create(['name' => 'manage content', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'content_manager', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        $user = User::factory()->create();
        $user->assignRole($role);

        $node = $this->makeNode([
            'visibility' => NavigationVisibility::Permissions,
            'requiredPermissionIds' => [(int) $perm->id],
        ]);

        $user = $user->fresh();

        $this->assertTrue($this->evaluator->isVisible($node, $user));
    }

    // ── PublishWindow ─────────────────────────────────────────────────────

    public function test_expired_publish_window_hides_all_visibility(): void
    {
        $node = $this->makeNode([
            'visibility' => NavigationVisibility::All,
            'publishWindow' => new PublishWindow(null, Carbon::now()->subHour()),
        ]);

        $this->assertFalse($this->evaluator->isVisible($node, null));
        $this->assertFalse($this->evaluator->isVisible($node, User::factory()->create()));
    }

    public function test_future_publish_window_hides_item(): void
    {
        $node = $this->makeNode([
            'visibility' => NavigationVisibility::All,
            'publishWindow' => new PublishWindow(Carbon::now()->addHour(), null),
        ]);

        $this->assertFalse($this->evaluator->isVisible($node, null));
    }

    public function test_active_publish_window_shows_item(): void
    {
        $node = $this->makeNode([
            'visibility' => NavigationVisibility::All,
            'publishWindow' => new PublishWindow(Carbon::now()->subHour(), Carbon::now()->addHour()),
        ]);

        $this->assertTrue($this->evaluator->isVisible($node, null));
    }
}
