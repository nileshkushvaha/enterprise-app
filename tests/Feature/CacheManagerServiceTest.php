<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\CacheManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CacheManagerServiceTest extends TestCase
{
    use RefreshDatabase;

    private CacheManagerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent real artisan commands from running during tests — they
        // can cache config and break the in-memory SQLite environment.
        Artisan::shouldReceive('call')->andReturn(0)->byDefault();
        Artisan::shouldReceive('output')->andReturn('')->byDefault();

        $this->service = app(CacheManagerService::class);
    }

    // ── System info ───────────────────────────────────────────────────────

    public function test_getCacheDriver_returns_configured_driver(): void
    {
        $driver = $this->service->getCacheDriver();
        $this->assertSame(config('cache.default'), $driver);
    }

    public function test_getCacheStore_returns_non_empty_string(): void
    {
        $store = $this->service->getCacheStore();
        $this->assertNotEmpty($store);
    }

    public function test_getEnvironment_returns_non_empty_string(): void
    {
        $this->assertNotEmpty($this->service->getEnvironment());
    }

    public function test_getLaravelVersion_matches_app_version(): void
    {
        $this->assertSame(app()->version(), $this->service->getLaravelVersion());
    }

    public function test_getPhpVersion_matches_php_version_constant(): void
    {
        $this->assertSame(PHP_VERSION, $this->service->getPhpVersion());
    }

    public function test_isConfigCached_returns_bool(): void
    {
        $this->assertIsBool($this->service->isConfigCached());
    }

    public function test_isRouteCached_returns_bool(): void
    {
        $this->assertIsBool($this->service->isRouteCached());
    }

    public function test_isViewCached_returns_bool(): void
    {
        $this->assertIsBool($this->service->isViewCached());
    }

    public function test_isEventCached_returns_bool(): void
    {
        $this->assertIsBool($this->service->isEventCached());
    }

    // ── Clear actions return correct shape ────────────────────────────────

    public function test_clearApplicationCache_returns_result_array(): void
    {
        $result = $this->service->clearApplicationCache();
        $this->assertResultShape($result);
    }

    public function test_clearViewCache_returns_result_array(): void
    {
        $result = $this->service->clearViewCache();
        $this->assertResultShape($result);
    }

    public function test_clearRouteCache_returns_result_array(): void
    {
        $result = $this->service->clearRouteCache();
        $this->assertResultShape($result);
    }

    public function test_clearConfigCache_returns_result_array(): void
    {
        $result = $this->service->clearConfigCache();
        $this->assertResultShape($result);
    }

    public function test_clearEventCache_returns_result_array(): void
    {
        $result = $this->service->clearEventCache();
        $this->assertResultShape($result);
    }

    public function test_optimize_returns_result_array(): void
    {
        $result = $this->service->optimize();
        $this->assertResultShape($result);
    }

    public function test_optimizeClear_returns_result_array(): void
    {
        $result = $this->service->optimizeClear();
        $this->assertResultShape($result);
    }

    public function test_successful_result_has_success_true(): void
    {
        // Artisan::call returns 0 (mocked in setUp)
        $result = $this->service->clearApplicationCache();
        $this->assertTrue($result['success']);
    }

    public function test_failed_exit_code_marks_success_false(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(1);
        Artisan::shouldReceive('output')->once()->andReturn('Something failed.');

        $result = $this->service->clearApplicationCache();

        $this->assertFalse($result['success']);
    }

    // ── Activity logging ───────────────────────────────────────────────────

    public function test_clearApplicationCache_logs_activity(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->service->clearApplicationCache();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'cache_manager',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function assertResultShape(array $result): void
    {
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('output', $result);
        $this->assertArrayHasKey('exitCode', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertIsBool($result['success']);
    }
}
