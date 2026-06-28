<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Filament\Pages\Security\RegistrationPage;
use App\Models\User;
use App\Settings\RegistrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/settings']);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'security.registration.view',   'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'security.registration.update', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $this->superAdmin->assignRole($superAdminRole);

        $this->regularUser = User::factory()->create(['status' => 'active']);
    }

    // ── Access control ─────────────────────────────────────────────────────

    public function test_super_admin_can_access_registration_page(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/security/registration')
            ->assertOk();
    }

    public function test_regular_user_cannot_access_registration_page(): void
    {
        $this->actingAs($this->regularUser)
            ->get('/admin/security/registration')
            ->assertForbidden();
    }

    // ── Default values ──────────────────────────────────────────────────────

    public function test_default_values_are_seeded_correctly(): void
    {
        $settings = app(RegistrationSettings::class);

        $this->assertFalse($settings->self_registration_enabled);
        $this->assertNull($settings->default_role);
        $this->assertFalse($settings->require_admin_approval);
        $this->assertTrue($settings->send_welcome_email);
        $this->assertFalse($settings->auto_verify_email);
        $this->assertFalse($settings->invitation_only);
        $this->assertFalse($settings->domain_restriction_enabled);
    }

    // ── Settings persistence ────────────────────────────────────────────────

    public function test_save_persists_settings(): void
    {
        $this->actingAs($this->superAdmin);

        // Create a role so the Select can find it
        $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

        Livewire::test(RegistrationPage::class)
            ->set('data.self_registration_enabled', true)
            ->set('data.default_role', 'editor')
            ->set('data.require_admin_approval', true)
            ->set('data.send_welcome_email', false)
            ->set('data.auto_verify_email', true)
            ->call('save');

        $settings = app()->make(RegistrationSettings::class)->refresh();

        $this->assertTrue($settings->self_registration_enabled);
        $this->assertSame('editor', $settings->default_role);
        $this->assertTrue($settings->require_admin_approval);
        $this->assertFalse($settings->send_welcome_email);
        $this->assertTrue($settings->auto_verify_email);
    }

    public function test_save_shows_success_notification(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(RegistrationPage::class)
            ->call('save')
            ->assertNotified('Registration settings saved');
    }

    public function test_default_role_can_be_null(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(RegistrationPage::class)
            ->set('data.default_role', null)
            ->call('save');

        $settings = app()->make(RegistrationSettings::class)->refresh();

        $this->assertNull($settings->default_role);
    }
}
