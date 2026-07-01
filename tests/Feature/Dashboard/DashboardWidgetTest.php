<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\RecentAuditTrailWidget;
use App\Filament\Widgets\RecentLoginsWidget;
use App\Filament\Widgets\RecentUsersWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Widget visibility is permission-driven: each widget has canView() which
 * Filament calls before rendering. Gate::before() handles the super_admin
 * bypass so no explicit isSuperAdmin() logic lives inside widgets.
 */
class DashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $manager;

    private const WIDGET_PERMISSIONS = [
        StatsOverviewWidget::class => 'View:StatsOverviewWidget',
        RecentUsersWidget::class => 'View:RecentUsersWidget',
        RecentLoginsWidget::class => 'View:RecentLoginsWidget',
        RecentAuditTrailWidget::class => 'View:RecentAuditTrailWidget',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        foreach (self::WIDGET_PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach ([
            'ViewAny:User', 'ViewAny:Page', 'ViewAny:Post', 'ViewAny:Role',
            'ViewAny:Activity', 'ViewAny:LoginHistory',
            'Create:User', 'Create:Page', 'Create:Post', 'Create:Role',
            'View:GeneralSettingsPage',
        ] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $this->superAdmin = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $this->superAdmin->assignRole($superAdminRole);

        $this->manager = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $this->manager->assignRole($managerRole);
    }

    // ── canView() via Gate::before() — super_admin sees everything ────────────

    public function test_super_admin_can_view_stats_widget(): void
    {
        $this->actingAs($this->superAdmin);
        $this->assertTrue(StatsOverviewWidget::canView());
    }

    public function test_super_admin_can_view_recent_users_widget(): void
    {
        $this->actingAs($this->superAdmin);
        $this->assertTrue(RecentUsersWidget::canView());
    }

    public function test_super_admin_can_view_recent_logins_widget(): void
    {
        $this->actingAs($this->superAdmin);
        $this->assertTrue(RecentLoginsWidget::canView());
    }

    public function test_super_admin_can_view_audit_trail_widget(): void
    {
        $this->actingAs($this->superAdmin);
        $this->assertTrue(RecentAuditTrailWidget::canView());
    }

    public function test_super_admin_can_view_quick_actions_widget(): void
    {
        $this->actingAs($this->superAdmin);
        $this->assertTrue(QuickActionsWidget::canView());
    }

    // ── Manager sees only explicitly assigned widgets ─────────────────────────

    public function test_manager_without_permissions_cannot_view_stats_widget(): void
    {
        $this->actingAs($this->manager);
        $this->assertFalse(StatsOverviewWidget::canView());
    }

    public function test_manager_with_permission_can_view_stats_widget(): void
    {
        $this->manager->givePermissionTo('View:StatsOverviewWidget');
        $this->actingAs($this->manager);

        $this->assertTrue(StatsOverviewWidget::canView());
    }

    public function test_manager_with_permission_can_view_recent_users_widget(): void
    {
        $this->manager->givePermissionTo('View:RecentUsersWidget');
        $this->actingAs($this->manager);

        $this->assertTrue(RecentUsersWidget::canView());
    }

    public function test_manager_without_permission_cannot_view_recent_users_widget(): void
    {
        $this->actingAs($this->manager);
        $this->assertFalse(RecentUsersWidget::canView());
    }

    // ── Dashboard::getWidgets() filters to only visible widgets ──────────────

    public function test_super_admin_dashboard_includes_all_widgets(): void
    {
        $this->actingAs($this->superAdmin);

        $widgets = (new Dashboard)->getWidgets();

        $this->assertContains(StatsOverviewWidget::class, $widgets);
        $this->assertContains(RecentUsersWidget::class, $widgets);
        $this->assertContains(RecentLoginsWidget::class, $widgets);
        $this->assertContains(RecentAuditTrailWidget::class, $widgets);
        $this->assertContains(QuickActionsWidget::class, $widgets);
    }

    public function test_manager_without_permissions_dashboard_contains_only_quick_actions(): void
    {
        // Manager has no widget permissions yet — only QuickActionsWidget
        // is visible (its canView() just requires authentication).
        $this->actingAs($this->manager);

        $widgets = (new Dashboard)->getWidgets();

        $this->assertContains(QuickActionsWidget::class, $widgets);
        $this->assertNotContains(StatsOverviewWidget::class, $widgets);
        $this->assertNotContains(RecentUsersWidget::class, $widgets);
        $this->assertNotContains(RecentLoginsWidget::class, $widgets);
        $this->assertNotContains(RecentAuditTrailWidget::class, $widgets);
    }

    public function test_dashboard_getwidgets_respects_can_view(): void
    {
        $this->manager->givePermissionTo('View:StatsOverviewWidget');
        $this->actingAs($this->manager);

        $widgets = (new Dashboard)->getWidgets();

        $this->assertContains(StatsOverviewWidget::class, $widgets);
        $this->assertNotContains(RecentUsersWidget::class, $widgets);
    }

    // ── Quick Actions — view vs create permission variants ────────────────────

    public function test_super_admin_quick_actions_shows_create_labels(): void
    {
        // super_admin has all permissions — sees the "create" variant of each card
        $this->actingAs($this->superAdmin);

        $actions = (new QuickActionsWidget)->getActions();
        $labels = array_column($actions, 'label');

        $this->assertContains('Create User', $labels);
        $this->assertContains('New Page', $labels);
        $this->assertContains('New Post', $labels);
        $this->assertContains('Create Role', $labels);
    }

    public function test_manager_with_view_any_user_sees_users_index_card(): void
    {
        // Manager has ViewAny:User but not Create:User — sees "Users" (index) card
        $this->manager->givePermissionTo('ViewAny:User');
        $this->actingAs($this->manager);

        $actions = (new QuickActionsWidget)->getActions();
        $labels = array_column($actions, 'label');
        $urls = array_column($actions, 'url');

        $this->assertContains('Users', $labels);
        $this->assertContains(route('filament.admin.resources.users.index'), $urls);
        $this->assertNotContains('Create User', $labels);
    }

    public function test_manager_with_create_user_sees_create_user_card(): void
    {
        // When Create:User is also granted, the card upgrades to "Create User"
        $this->manager->givePermissionTo(['ViewAny:User', 'Create:User']);
        $this->actingAs($this->manager);

        $actions = (new QuickActionsWidget)->getActions();
        $labels = array_column($actions, 'label');
        $urls = array_column($actions, 'url');

        $this->assertContains('Create User', $labels);
        $this->assertContains(route('filament.admin.resources.users.create'), $urls);
    }

    public function test_manager_with_default_permissions_sees_correct_cards(): void
    {
        // Default manager permissions: ViewAny:User/Role/Post/Page/Activity/LoginHistory
        $this->manager->givePermissionTo([
            'ViewAny:User', 'ViewAny:Role', 'ViewAny:Post',
            'ViewAny:Page', 'ViewAny:Activity', 'ViewAny:LoginHistory',
        ]);
        $this->actingAs($this->manager);

        $actions = (new QuickActionsWidget)->getActions();
        $labels = array_column($actions, 'label');

        $this->assertContains('Users', $labels);
        $this->assertContains('Pages', $labels);
        $this->assertContains('Posts', $labels);
        $this->assertContains('Roles', $labels);
        $this->assertContains('Activity Log', $labels);
        $this->assertContains('Login History', $labels);
        $this->assertNotContains('Create User', $labels);
        $this->assertNotContains('Settings', $labels);
    }

    public function test_manager_with_no_permissions_sees_no_quick_action_cards(): void
    {
        $this->actingAs($this->manager);
        $this->assertEmpty((new QuickActionsWidget)->getActions());
    }

    // ── Widget registration is extensible ─────────────────────────────────────

    public function test_widget_list_is_defined_on_dashboard_page(): void
    {
        $reflection = new \ReflectionClass(Dashboard::class);
        $constant = $reflection->getConstant('WIDGETS');

        $this->assertIsArray($constant);
        $this->assertNotEmpty($constant);

        foreach ($constant as $widgetClass) {
            $this->assertTrue(
                method_exists($widgetClass, 'canView'),
                "{$widgetClass} must implement canView()"
            );
        }
    }

    // ── canView() prevents widgets loading on the dashboard page ────────────
    // Filament uses canView() as a filter in getWidgets(), not as an HTTP
    // guard — a widget excluded from getWidgets() never mounts, so its
    // queries never run. The authoritative test is Dashboard::getWidgets().

    public function test_excluded_widget_is_absent_from_dashboard_getwidgets(): void
    {
        $this->actingAs($this->manager);
        // Manager has no widget permissions — stats widget must be absent.
        $this->assertNotContains(StatsOverviewWidget::class, (new Dashboard)->getWidgets());
    }

    public function test_stats_widget_renders_for_super_admin(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(StatsOverviewWidget::class)
            ->assertSuccessful();
    }
}
