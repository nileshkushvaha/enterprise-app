<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Filament\Pages\Security\AuthenticationPage;
use App\Models\User;
use App\Settings\AuthenticationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $regularUser;

    private User $securityUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/settings']);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'security.authentication.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'security.authentication.update', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $this->superAdmin->assignRole($superAdminRole);

        $this->regularUser = User::factory()->create(['status' => 'active']);

        $this->securityUser = User::factory()->create(['status' => 'active']);
        $this->securityUser->givePermissionTo($permission);
    }

    // ── Access control ─────────────────────────────────────────────────────

    public function test_super_admin_can_access_authentication_page(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/security/authentication')
            ->assertOk();
    }

    public function test_user_with_permission_can_access_authentication_page(): void
    {
        $this->actingAs($this->securityUser)
            ->get('/admin/security/authentication')
            ->assertOk();
    }

    public function test_regular_user_cannot_access_authentication_page(): void
    {
        $this->actingAs($this->regularUser)
            ->get('/admin/security/authentication')
            ->assertForbidden();
    }

    // ── Navigation registration ─────────────────────────────────────────────

    public function test_navigation_is_registered_for_super_admin(): void
    {
        $this->actingAs($this->superAdmin);

        $this->assertTrue(AuthenticationPage::shouldRegisterNavigation());
    }

    public function test_navigation_is_not_registered_for_regular_user(): void
    {
        $this->actingAs($this->regularUser);

        $this->assertFalse(AuthenticationPage::shouldRegisterNavigation());
    }

    // ── Default values ──────────────────────────────────────────────────────

    public function test_default_values_are_seeded_correctly(): void
    {
        $settings = app(AuthenticationSettings::class);

        $this->assertTrue($settings->login_enabled);
        $this->assertTrue($settings->remember_me_enabled);
        $this->assertTrue($settings->email_verification_required);
        $this->assertSame('email', $settings->default_login_method);
        $this->assertFalse($settings->two_factor_enabled);
        $this->assertFalse($settings->passkeys_enabled);
        $this->assertFalse($settings->social_login_enabled);
        $this->assertFalse($settings->ldap_enabled);
        $this->assertFalse($settings->saml_enabled);
        $this->assertFalse($settings->azure_ad_enabled);
    }

    // ── Settings persistence ────────────────────────────────────────────────

    public function test_page_mounts_with_current_settings(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(AuthenticationPage::class)
            ->assertSet('data.login_enabled', true)
            ->assertSet('data.remember_me_enabled', true)
            ->assertSet('data.default_login_method', 'email');
    }

    public function test_save_persists_settings(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(AuthenticationPage::class)
            ->set('data.login_enabled', false)
            ->set('data.remember_me_enabled', false)
            ->set('data.email_verification_required', false)
            ->set('data.default_login_method', 'email')
            ->call('save');

        $settings = app()->make(AuthenticationSettings::class)->refresh();

        $this->assertFalse($settings->login_enabled);
        $this->assertFalse($settings->remember_me_enabled);
        $this->assertFalse($settings->email_verification_required);
    }

    public function test_save_shows_success_notification(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(AuthenticationPage::class)
            ->set('data.login_enabled', true)
            ->call('save')
            ->assertNotified('Authentication settings saved');
    }

    // ── Validation ──────────────────────────────────────────────────────────

    public function test_default_login_method_is_required(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(AuthenticationPage::class)
            ->set('data.default_login_method', '')
            ->call('save')
            ->assertHasErrors(['data.default_login_method']);
    }

    // ── Activity log ────────────────────────────────────────────────────────

    public function test_save_creates_activity_log_entry(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(AuthenticationPage::class)
            ->set('data.login_enabled', true)
            ->call('save');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'security',
            'event' => 'settings_updated',
        ]);
    }

    public function test_save_logs_changed_fields_diff(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(AuthenticationPage::class)
            ->set('data.login_enabled', false)
            ->set('data.remember_me_enabled', false)
            ->call('save');

        $log = Activity::where('log_name', 'security')
            ->where('event', 'settings_updated')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $changes = $log->properties['changes'] ?? [];
        $this->assertArrayHasKey('login_enabled', $changes);
        $this->assertTrue($changes['login_enabled']['old']);
        $this->assertFalse($changes['login_enabled']['new']);
    }

    // ── Update permission enforcement ───────────────────────────────────────

    public function test_user_with_only_view_permission_cannot_save(): void
    {
        $viewOnlyUser = User::factory()->create(['status' => 'active']);
        $viewOnlyUser->givePermissionTo(
            Permission::firstOrCreate(['name' => 'security.authentication.view', 'guard_name' => 'web'])
        );

        $this->actingAs($viewOnlyUser);

        Livewire::test(AuthenticationPage::class)
            ->call('save')
            ->assertForbidden();
    }

    public function test_user_with_update_permission_can_save(): void
    {
        $updateUser = User::factory()->create(['status' => 'active']);
        $updateUser->givePermissionTo([
            Permission::firstOrCreate(['name' => 'security.authentication.view',  'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'security.authentication.update', 'guard_name' => 'web']),
        ]);

        $this->actingAs($updateUser);

        Livewire::test(AuthenticationPage::class)
            ->set('data.login_enabled', true)
            ->call('save')
            ->assertHasNoErrors();
    }
}
