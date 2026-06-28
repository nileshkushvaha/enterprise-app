<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Filament\Pages\Security\LoginSecurityPage;
use App\Models\User;
use App\Settings\LoginSecuritySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginSecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/settings']);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'security.login_security.view',   'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'security.login_security.update', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $this->superAdmin->assignRole($superAdminRole);

        $this->regularUser = User::factory()->create(['status' => 'active']);
    }

    // ── Access control ─────────────────────────────────────────────────────

    public function test_super_admin_can_access_login_security_page(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/security/login-security')
            ->assertOk();
    }

    public function test_regular_user_cannot_access_login_security_page(): void
    {
        $this->actingAs($this->regularUser)
            ->get('/admin/security/login-security')
            ->assertForbidden();
    }

    // ── Default values ──────────────────────────────────────────────────────

    public function test_default_values_are_seeded_correctly(): void
    {
        $settings = app(LoginSecuritySettings::class);

        $this->assertSame(5, $settings->max_failed_attempts);
        $this->assertSame(15, $settings->lockout_duration);
        $this->assertTrue($settings->throttling_enabled);
        $this->assertTrue($settings->reset_throttling_enabled);
        $this->assertTrue($settings->notify_user_on_failed);
        $this->assertFalse($settings->notify_admin_on_lock);
        $this->assertFalse($settings->recaptcha_enabled);
        $this->assertFalse($settings->turnstile_enabled);
    }

    // ── Settings persistence ────────────────────────────────────────────────

    public function test_save_persists_settings(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(LoginSecurityPage::class)
            ->set('data.max_failed_attempts', 3)
            ->set('data.lockout_duration', 30)
            ->set('data.throttling_enabled', false)
            ->set('data.notify_admin_on_lock', true)
            ->call('save');

        $settings = app()->make(LoginSecuritySettings::class)->refresh();

        $this->assertSame(3, $settings->max_failed_attempts);
        $this->assertSame(30, $settings->lockout_duration);
        $this->assertFalse($settings->throttling_enabled);
        $this->assertTrue($settings->notify_admin_on_lock);
    }

    public function test_save_shows_success_notification(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(LoginSecurityPage::class)
            ->call('save')
            ->assertNotified('Login security settings saved');
    }

    // ── Validation ──────────────────────────────────────────────────────────

    public function test_max_failed_attempts_must_be_at_least_one(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(LoginSecurityPage::class)
            ->set('data.max_failed_attempts', 0)
            ->call('save')
            ->assertHasErrors(['data.max_failed_attempts']);
    }

    public function test_lockout_duration_must_be_at_least_one(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(LoginSecurityPage::class)
            ->set('data.lockout_duration', 0)
            ->call('save')
            ->assertHasErrors(['data.lockout_duration']);
    }
}
