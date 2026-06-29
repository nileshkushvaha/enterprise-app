<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Profile\ProfileService;
use App\Services\Security\PasswordRuleBuilder;
use App\Settings\PasswordPolicySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Verifies PasswordRuleBuilder is the single source of truth across all
 * password-accepting flows: registration, reset, profile change, admin form.
 */
class PasswordPolicyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/settings']);
    }

    // ── PasswordRuleBuilder reads settings ─────────────────────────────────

    public function test_rule_builder_enforces_min_length(): void
    {
        $s = app(PasswordPolicySettings::class);
        $s->min_length = 12;
        $s->require_uppercase = false;
        $s->require_lowercase = false;
        $s->require_number = false;
        $s->require_special = false;
        $s->save();

        $errors = $this->validate('short', app(PasswordRuleBuilder::class)->build());

        $this->assertNotEmpty($errors);
    }

    public function test_rule_builder_enforces_uppercase(): void
    {
        $s = app(PasswordPolicySettings::class);
        $s->min_length = 6;
        $s->require_uppercase = true;
        $s->require_lowercase = false;
        $s->require_number = false;
        $s->require_special = false;
        $s->save();

        $this->assertNotEmpty($this->validate('alllower', app(PasswordRuleBuilder::class)->build()));
        $this->assertEmpty($this->validate('HasUpper', app(PasswordRuleBuilder::class)->build()));
    }

    public function test_rule_builder_enforces_lowercase(): void
    {
        $s = app(PasswordPolicySettings::class);
        $s->min_length = 6;
        $s->require_uppercase = false;
        $s->require_lowercase = true;
        $s->require_number = false;
        $s->require_special = false;
        $s->save();

        $this->assertNotEmpty($this->validate('ALLUPPER', app(PasswordRuleBuilder::class)->build()));
        $this->assertEmpty($this->validate('hasLower', app(PasswordRuleBuilder::class)->build()));
    }

    public function test_rule_builder_enforces_number(): void
    {
        $s = app(PasswordPolicySettings::class);
        $s->min_length = 6;
        $s->require_uppercase = false;
        $s->require_lowercase = false;
        $s->require_number = true;
        $s->require_special = false;
        $s->save();

        $this->assertNotEmpty($this->validate('noNumbers', app(PasswordRuleBuilder::class)->build()));
        $this->assertEmpty($this->validate('has1number', app(PasswordRuleBuilder::class)->build()));
    }

    public function test_rule_builder_enforces_special_character(): void
    {
        $s = app(PasswordPolicySettings::class);
        $s->min_length = 6;
        $s->require_uppercase = false;
        $s->require_lowercase = false;
        $s->require_number = false;
        $s->require_special = true;
        $s->save();

        $this->assertNotEmpty($this->validate('NoSymbol', app(PasswordRuleBuilder::class)->build()));
        $this->assertEmpty($this->validate('has!sym', app(PasswordRuleBuilder::class)->build()));
    }

    public function test_policy_change_immediately_affects_reset_password_form(): void
    {
        $s = app(PasswordPolicySettings::class);
        $s->min_length = 6;
        $s->require_uppercase = false;
        $s->require_lowercase = false;
        $s->require_number = false;
        $s->require_special = false;
        $s->save();

        // Previously valid
        $this->assertEmpty($this->validate('simple', app(PasswordRuleBuilder::class)->build()));

        // Raise the bar
        $s->min_length = 20;
        $s->save();

        $this->assertNotEmpty($this->validate('simple', app(PasswordRuleBuilder::class)->build()));
    }

    // ── No hardcoded rules in ResetPasswordRequest ─────────────────────────

    public function test_reset_password_uses_policy_min_length(): void
    {
        // Set policy to 16-char minimum
        $s = app(PasswordPolicySettings::class);
        $s->min_length = 16;
        $s->require_uppercase = false;
        $s->require_lowercase = false;
        $s->require_number = false;
        $s->require_special = false;
        $s->save();

        // A 9-char password that would pass the old hardcoded Password::min(8) fails now
        $validator = Validator::make(
            ['token' => 'abc', 'email' => 'x@y.com', 'password' => 'Short123!', 'password_confirmation' => 'Short123!'],
            (new ResetPasswordRequest)->rules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_reset_password_passes_when_policy_satisfied(): void
    {
        $s = app(PasswordPolicySettings::class);
        $s->min_length = 6;
        $s->require_uppercase = false;
        $s->require_lowercase = false;
        $s->require_number = false;
        $s->require_special = false;
        $s->save();

        $validator = Validator::make(
            ['token' => 'abc', 'email' => 'x@y.com', 'password' => 'simple', 'password_confirmation' => 'simple'],
            (new ResetPasswordRequest)->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    // ── Password changed at is always updated ──────────────────────────────

    public function test_password_changed_at_updated_on_profile_change(): void
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);

        app(ProfileService::class)->changePassword($user, 'NewPassword1!');

        $this->assertNotNull($user->fresh()->password_changed_at);
    }

    public function test_must_change_password_cleared_on_profile_change(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        app(ProfileService::class)->changePassword($user, 'NewPassword1!');

        $this->assertFalse($user->fresh()->must_change_password);
    }

    // ── Helper ─────────────────────────────────────────────────────────────

    private function validate(string $password, mixed $rule): array
    {
        return Validator::make(
            ['password' => $password],
            ['password' => $rule],
        )->errors()->get('password');
    }
}
