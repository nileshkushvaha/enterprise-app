<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Filament\Pages\Security\PasswordPolicyPage;
use App\Models\User;
use App\Settings\PasswordPolicySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordPolicySettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/settings']);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'security.password_policy.view',   'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'security.password_policy.update', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $this->superAdmin->assignRole($superAdminRole);

        $this->regularUser = User::factory()->create(['status' => 'active']);
    }

    // ── Access control ─────────────────────────────────────────────────────

    public function test_super_admin_can_access_password_policy_page(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/security/password-policy')
            ->assertOk();
    }

    public function test_regular_user_cannot_access_password_policy_page(): void
    {
        $this->actingAs($this->regularUser)
            ->get('/admin/security/password-policy')
            ->assertForbidden();
    }

    // ── Default values ──────────────────────────────────────────────────────

    public function test_default_values_are_seeded_correctly(): void
    {
        $settings = app(PasswordPolicySettings::class);

        $this->assertSame(8, $settings->min_length);
        $this->assertTrue($settings->require_uppercase);
        $this->assertTrue($settings->require_lowercase);
        $this->assertTrue($settings->require_number);
        $this->assertFalse($settings->require_special);
        $this->assertFalse($settings->prevent_reuse);
        $this->assertSame(5, $settings->password_history_count);
        $this->assertFalse($settings->expiry_enabled);
        $this->assertSame(90, $settings->expiry_days);
        $this->assertFalse($settings->force_change_on_first_login);
    }

    // ── Settings persistence ────────────────────────────────────────────────

    public function test_save_persists_settings(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(PasswordPolicyPage::class)
            ->set('data.min_length', 12)
            ->set('data.require_special', true)
            ->set('data.prevent_reuse', true)
            ->set('data.password_history_count', 10)
            ->set('data.expiry_enabled', true)
            ->set('data.expiry_days', 60)
            ->set('data.force_change_on_first_login', true)
            ->call('save');

        $settings = app()->make(PasswordPolicySettings::class)->refresh();

        $this->assertSame(12, $settings->min_length);
        $this->assertTrue($settings->require_special);
        $this->assertTrue($settings->prevent_reuse);
        $this->assertSame(10, $settings->password_history_count);
        $this->assertTrue($settings->expiry_enabled);
        $this->assertSame(60, $settings->expiry_days);
        $this->assertTrue($settings->force_change_on_first_login);
    }

    public function test_save_shows_success_notification(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(PasswordPolicyPage::class)
            ->call('save')
            ->assertNotified('Password policy saved');
    }

    // ── Validation ──────────────────────────────────────────────────────────

    public function test_min_length_must_be_at_least_one(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(PasswordPolicyPage::class)
            ->set('data.min_length', 0)
            ->call('save')
            ->assertHasErrors(['data.min_length']);
    }

    public function test_min_length_must_be_numeric(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(PasswordPolicyPage::class)
            ->set('data.min_length', 'abc')
            ->call('save')
            ->assertHasErrors(['data.min_length']);
    }

    public function test_expiry_days_must_be_at_least_one(): void
    {
        $this->actingAs($this->superAdmin);

        // expiry_days is only visible (and validated) when expiry_enabled=true
        Livewire::test(PasswordPolicyPage::class)
            ->set('data.expiry_enabled', true)
            ->set('data.expiry_days', 0)
            ->call('save')
            ->assertHasErrors(['data.expiry_days']);
    }

    // ── Future-ready architecture ───────────────────────────────────────────

    public function test_password_history_count_is_configurable(): void
    {
        $settings = app(PasswordPolicySettings::class);
        $settings->password_history_count = 12;
        $settings->save();

        $this->assertSame(12, app()->make(PasswordPolicySettings::class)->refresh()->password_history_count);
    }

    public function test_expiry_days_is_configurable(): void
    {
        $settings = app(PasswordPolicySettings::class);
        $settings->expiry_days = 30;
        $settings->save();

        $this->assertSame(30, app()->make(PasswordPolicySettings::class)->refresh()->expiry_days);
    }

    public function test_password_histories_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('user_password_histories'));
    }
}
