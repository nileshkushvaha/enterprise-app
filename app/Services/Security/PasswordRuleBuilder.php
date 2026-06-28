<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Settings\PasswordPolicySettings;
use Illuminate\Validation\Rules\Password;

class PasswordRuleBuilder
{
    public function __construct(private readonly PasswordPolicySettings $settings) {}

    public function build(): Password
    {
        $rule = Password::min($this->settings->min_length);

        if ($this->settings->require_uppercase || $this->settings->require_lowercase) {
            $rule = $rule->mixedCase();
        }

        if ($this->settings->require_number) {
            $rule = $rule->numbers();
        }

        if ($this->settings->require_special) {
            $rule = $rule->symbols();
        }

        return $rule;
    }
}
