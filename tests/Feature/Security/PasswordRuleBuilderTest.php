<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Services\Security\PasswordRuleBuilder;
use App\Settings\PasswordPolicySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class PasswordRuleBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/settings']);
    }

    // ── Build returns Password instance ────────────────────────────────────

    public function test_build_returns_password_rule_instance(): void
    {
        $rule = app(PasswordRuleBuilder::class)->build();

        $this->assertInstanceOf(Password::class, $rule);
    }

    // ── Min length from settings ───────────────────────────────────────────

    public function test_min_length_comes_from_settings(): void
    {
        $settings = app(PasswordPolicySettings::class);
        $settings->min_length = 12;
        $settings->save();

        $errors = $this->validatePassword('short', app(PasswordRuleBuilder::class)->build());

        $this->assertNotEmpty($errors);
    }

    public function test_password_meeting_min_length_passes(): void
    {
        $settings = app(PasswordPolicySettings::class);
        $settings->min_length = 6;
        $settings->require_uppercase = false;
        $settings->require_lowercase = false;
        $settings->require_number = false;
        $settings->require_special = false;
        $settings->save();

        $errors = $this->validatePassword('abcdef', app(PasswordRuleBuilder::class)->build());

        $this->assertEmpty($errors);
    }

    // ── Mixed case ─────────────────────────────────────────────────────────

    public function test_mixed_case_required_when_uppercase_enabled(): void
    {
        $settings = app(PasswordPolicySettings::class);
        $settings->min_length = 8;
        $settings->require_uppercase = true;
        $settings->require_lowercase = false;
        $settings->require_number = false;
        $settings->require_special = false;
        $settings->save();

        $errors = $this->validatePassword('alllower', app(PasswordRuleBuilder::class)->build());

        $this->assertNotEmpty($errors);
    }

    public function test_mixed_case_not_required_when_both_disabled(): void
    {
        $settings = app(PasswordPolicySettings::class);
        $settings->min_length = 8;
        $settings->require_uppercase = false;
        $settings->require_lowercase = false;
        $settings->require_number = false;
        $settings->require_special = false;
        $settings->save();

        $errors = $this->validatePassword('alllower', app(PasswordRuleBuilder::class)->build());

        $this->assertEmpty($errors);
    }

    // ── Numbers ────────────────────────────────────────────────────────────

    public function test_number_required_when_enabled(): void
    {
        $settings = app(PasswordPolicySettings::class);
        $settings->min_length = 8;
        $settings->require_uppercase = false;
        $settings->require_lowercase = false;
        $settings->require_number = true;
        $settings->require_special = false;
        $settings->save();

        $errors = $this->validatePassword('noNumbers', app(PasswordRuleBuilder::class)->build());

        $this->assertNotEmpty($errors);
    }

    public function test_number_not_required_when_disabled(): void
    {
        $settings = app(PasswordPolicySettings::class);
        $settings->min_length = 8;
        $settings->require_uppercase = false;
        $settings->require_lowercase = false;
        $settings->require_number = false;
        $settings->require_special = false;
        $settings->save();

        $errors = $this->validatePassword('nonumbers', app(PasswordRuleBuilder::class)->build());

        $this->assertEmpty($errors);
    }

    // ── Symbols ────────────────────────────────────────────────────────────

    public function test_symbol_required_when_enabled(): void
    {
        $settings = app(PasswordPolicySettings::class);
        $settings->min_length = 8;
        $settings->require_uppercase = false;
        $settings->require_lowercase = false;
        $settings->require_number = false;
        $settings->require_special = true;
        $settings->save();

        $errors = $this->validatePassword('NoSymbol1', app(PasswordRuleBuilder::class)->build());

        $this->assertNotEmpty($errors);
    }

    public function test_symbol_not_required_when_disabled(): void
    {
        $settings = app(PasswordPolicySettings::class);
        $settings->min_length = 8;
        $settings->require_uppercase = false;
        $settings->require_lowercase = false;
        $settings->require_number = false;
        $settings->require_special = false;
        $settings->save();

        $errors = $this->validatePassword('nosymbol1', app(PasswordRuleBuilder::class)->build());

        $this->assertEmpty($errors);
    }

    // ── Single source of truth ─────────────────────────────────────────────

    public function test_changing_settings_changes_validation(): void
    {
        $settings = app(PasswordPolicySettings::class);
        $settings->min_length = 6;
        $settings->require_special = false;
        $settings->require_number = false;
        $settings->require_uppercase = false;
        $settings->require_lowercase = false;
        $settings->save();

        // Previously-valid password
        $this->assertEmpty($this->validatePassword('simple', app(PasswordRuleBuilder::class)->build()));

        // Tighten the policy
        $settings->min_length = 20;
        $settings->save();

        $this->assertNotEmpty($this->validatePassword('simple', app(PasswordRuleBuilder::class)->build()));
    }

    // ── Helper ─────────────────────────────────────────────────────────────

    private function validatePassword(string $password, Password $rule): array
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => $rule],
        );

        return $validator->errors()->get('password');
    }
}
