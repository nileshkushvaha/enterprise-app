<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\CacheManagerPage;
use App\Models\User;
use App\Services\CacheManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    // ── canAccess / shouldRegisterNavigation ──────────────────────────────

    public function test_canAccess_returns_false_for_regular_user(): void
    {
        $this->actingAs($this->regularUser);
        $this->assertFalse(CacheManagerPage::canAccess());
    }

    public function test_canAccess_returns_true_for_super_admin(): void
    {
        $this->actingAs($this->superAdmin);
        $this->assertTrue(CacheManagerPage::canAccess());
    }

    // ── Actions via Livewire ──────────────────────────────────────────────

    public function test_super_admin_can_clear_application_cache(): void
    {
        $this->mock(CacheManagerService::class, function ($mock) {
            $mock->shouldReceive('clearApplicationCache')
                ->once()
                ->andReturn([
                    'success'   => true,
                    'message'   => 'Application cache cleared successfully.',
                    'output'    => 'Application cache cleared successfully.',
                    'exitCode'  => 0,
                    'timestamp' => now()->toDateTimeString(),
                ]);

            $this->stubInfoMethods($mock);
        });

        $this->actingAs($this->superAdmin);

        Livewire::test(CacheManagerPage::class)
            ->callAction('clear_app_cache')
            ->assertHasNoErrors()
            ->assertSet('lastResult.success', true);
    }

    public function test_action_sets_last_result_on_failure(): void
    {
        $this->mock(CacheManagerService::class, function ($mock) {
            $mock->shouldReceive('clearApplicationCache')
                ->once()
                ->andReturn([
                    'success'   => false,
                    'message'   => 'Command failed.',
                    'output'    => 'Something went wrong.',
                    'exitCode'  => 1,
                    'timestamp' => now()->toDateTimeString(),
                ]);

            $this->stubInfoMethods($mock);
        });

        $this->actingAs($this->superAdmin);

        Livewire::test(CacheManagerPage::class)
            ->callAction('clear_app_cache')
            ->assertSet('lastResult.success', false);
    }

    public function test_optimize_action_calls_service(): void
    {
        $this->mock(CacheManagerService::class, function ($mock) {
            $mock->shouldReceive('optimize')
                ->once()
                ->andReturn([
                    'success'   => true,
                    'message'   => 'Application optimized successfully.',
                    'output'    => 'Application optimized successfully.',
                    'exitCode'  => 0,
                    'timestamp' => now()->toDateTimeString(),
                ]);

            $this->stubInfoMethods($mock);
        });

        $this->actingAs($this->superAdmin);

        Livewire::test(CacheManagerPage::class)
            ->callAction('optimize')
            ->assertSet('lastResult.success', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function stubInfoMethods(\Mockery\MockInterface $mock): void
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
