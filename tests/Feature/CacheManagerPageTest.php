<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\CacheManagerPage;
use App\Models\User;
use App\Policies\CacheManagerPolicy;
use App\Services\CacheManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CacheManagerPageTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'cache_manager.view',     'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'cache_manager.clear',    'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'cache_manager.optimize', 'guard_name' => 'web']);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $this->superAdmin->assignRole($superAdminRole);

        $this->regularUser = User::factory()->create(['status' => 'active']);
    }

    // ── Access control ─────────────────────────────────────────────────────

    public function test_super_admin_can_access_cache_manager_page(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/system/cache-manager')
            ->assertOk();
    }

    public function test_regular_user_cannot_access_cache_manager_page(): void
    {
        $this->actingAs($this->regularUser)
            ->get('/admin/system/cache-manager')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_cache_manager_page(): void
    {
        $this->get('/admin/system/cache-manager')
            ->assertRedirect();
    }

    // ── canAccess ──────────────────────────────────────────────────────────

    public function test_can_access_returns_false_for_regular_user(): void
    {
        $this->actingAs($this->regularUser);
        $this->assertFalse(CacheManagerPage::canAccess());
    }

    public function test_can_access_returns_true_for_super_admin(): void
    {
        $this->actingAs($this->superAdmin);
        $this->assertTrue(CacheManagerPage::canAccess());
    }

    public function test_can_access_returns_true_for_user_with_view_permission(): void
    {
        $this->regularUser->givePermissionTo('cache_manager.view');
        $this->actingAs($this->regularUser);

        $this->assertTrue(CacheManagerPage::canAccess());
    }

    // ── Policy ─────────────────────────────────────────────────────────────

    public function test_policy_view_page_returns_false_without_permission(): void
    {
        $this->assertFalse(app(CacheManagerPolicy::class)->viewPage($this->regularUser));
    }

    public function test_policy_view_page_returns_true_with_permission(): void
    {
        $this->regularUser->givePermissionTo('cache_manager.view');
        $this->assertTrue(app(CacheManagerPolicy::class)->viewPage($this->regularUser));
    }

    public function test_policy_view_page_does_not_throw_when_permission_does_not_exist(): void
    {
        // Delete the permission to simulate a fresh install where shield:generate hasn't run
        Permission::where('name', 'cache_manager.view')->delete();
        app()['cache']->forget('spatie.permission.cache');

        $result = app(CacheManagerPolicy::class)->viewPage($this->regularUser);

        $this->assertFalse($result);
    }

    public function test_policy_clear_application_cache_does_not_throw_when_permission_missing(): void
    {
        Permission::where('name', 'cache_manager.clear')->delete();
        app()['cache']->forget('spatie.permission.cache');

        $result = app(CacheManagerPolicy::class)->clearApplicationCache($this->regularUser);

        $this->assertFalse($result);
    }

    public function test_policy_optimize_does_not_throw_when_permission_missing(): void
    {
        Permission::where('name', 'cache_manager.optimize')->delete();
        app()['cache']->forget('spatie.permission.cache');

        $result = app(CacheManagerPolicy::class)->optimize($this->regularUser);

        $this->assertFalse($result);
    }

    // ── Actions via Livewire — all 7 methods ──────────────────────────────

    public function test_clear_app_cache_action_calls_service(): void
    {
        $this->mockService('clearApplicationCache', 'Application cache cleared successfully.');
        $this->actingAs($this->superAdmin);

        Livewire::test(CacheManagerPage::class)
            ->callAction('clear_app_cache')
            ->assertHasNoErrors()
            ->assertSet('lastResult.success', true);
    }

    public function test_clear_view_cache_action_calls_service(): void
    {
        $this->mockService('clearViewCache', 'View cache cleared successfully.');
        $this->actingAs($this->superAdmin);

        Livewire::test(CacheManagerPage::class)
            ->callAction('clear_view_cache')
            ->assertHasNoErrors()
            ->assertSet('lastResult.success', true);
    }

    public function test_clear_route_cache_action_calls_service(): void
    {
        $this->mockService('clearRouteCache', 'Route cache cleared successfully.');
        $this->actingAs($this->superAdmin);

        Livewire::test(CacheManagerPage::class)
            ->callAction('clear_route_cache')
            ->assertHasNoErrors()
            ->assertSet('lastResult.success', true);
    }

    public function test_clear_config_cache_action_calls_service(): void
    {
        $this->mockService('clearConfigCache', 'Config cache cleared successfully.');
        $this->actingAs($this->superAdmin);

        Livewire::test(CacheManagerPage::class)
            ->callAction('clear_config_cache')
            ->assertHasNoErrors()
            ->assertSet('lastResult.success', true);
    }

    public function test_clear_event_cache_action_calls_service(): void
    {
        $this->mockService('clearEventCache', 'Event cache cleared successfully.');
        $this->actingAs($this->superAdmin);

        Livewire::test(CacheManagerPage::class)
            ->callAction('clear_event_cache')
            ->assertHasNoErrors()
            ->assertSet('lastResult.success', true);
    }

    public function test_optimize_action_calls_service(): void
    {
        $this->mockService('optimize', 'Application optimized successfully.');
        $this->actingAs($this->superAdmin);

        Livewire::test(CacheManagerPage::class)
            ->callAction('optimize')
            ->assertHasNoErrors()
            ->assertSet('lastResult.success', true);
    }

    public function test_optimize_clear_action_calls_service(): void
    {
        $this->mockService('optimizeClear', 'All caches cleared successfully.');
        $this->actingAs($this->superAdmin);

        Livewire::test(CacheManagerPage::class)
            ->callAction('optimize_clear')
            ->assertHasNoErrors()
            ->assertSet('lastResult.success', true);
    }

    public function test_action_sets_last_result_on_failure(): void
    {
        $this->mock(CacheManagerService::class, function ($mock) {
            $mock->shouldReceive('clearApplicationCache')
                ->once()
                ->andReturn([
                    'success' => false,
                    'message' => 'Command failed.',
                    'output' => 'Something went wrong.',
                    'exitCode' => 1,
                    'timestamp' => now()->toDateTimeString(),
                ]);

            $this->stubInfoMethods($mock);
        });

        $this->actingAs($this->superAdmin);

        Livewire::test(CacheManagerPage::class)
            ->callAction('clear_app_cache')
            ->assertSet('lastResult.success', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function mockService(string $method, string $successMessage): void
    {
        $this->mock(CacheManagerService::class, function ($mock) use ($method, $successMessage) {
            $mock->shouldReceive($method)
                ->once()
                ->andReturn([
                    'success' => true,
                    'message' => $successMessage,
                    'output' => $successMessage,
                    'exitCode' => 0,
                    'timestamp' => now()->toDateTimeString(),
                ]);

            $this->stubInfoMethods($mock);
        });
    }

    private function stubInfoMethods(MockInterface $mock): void
    {
        $mock->shouldReceive('getCacheDriver')->andReturn('array')->byDefault();
        $mock->shouldReceive('getCacheStore')->andReturn('Array (in-memory)')->byDefault();
        $mock->shouldReceive('isConfigCached')->andReturn(false)->byDefault();
        $mock->shouldReceive('isRouteCached')->andReturn(false)->byDefault();
        $mock->shouldReceive('isViewCached')->andReturn(false)->byDefault();
        $mock->shouldReceive('isEventCached')->andReturn(false)->byDefault();
        $mock->shouldReceive('getEnvironment')->andReturn('testing')->byDefault();
        $mock->shouldReceive('getLaravelVersion')->andReturn(app()->version())->byDefault();
        $mock->shouldReceive('getPhpVersion')->andReturn(PHP_VERSION)->byDefault();
    }
}
