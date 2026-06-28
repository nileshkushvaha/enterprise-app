<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Settings\AccountProtectionSettings;
use App\Settings\AuthenticationSettings;
use App\Settings\LoginSecuritySettings;
use App\Settings\PasswordPolicySettings;
use App\Settings\RegistrationSettings;
use App\Settings\SessionSettings;

class SecuritySettingsService
{
    private const SENSITIVE_FIELDS = ['password', 'secret', 'key', 'token'];

    public function saveAuthentication(array $data, AuthenticationSettings $settings): void
    {
        $old = [
            'login_enabled' => $settings->login_enabled,
            'remember_me_enabled' => $settings->remember_me_enabled,
            'email_verification_required' => $settings->email_verification_required,
            'default_login_method' => $settings->default_login_method,
        ];

        $settings->login_enabled = (bool) ($data['login_enabled'] ?? true);
        $settings->remember_me_enabled = (bool) ($data['remember_me_enabled'] ?? true);
        $settings->email_verification_required = (bool) ($data['email_verification_required'] ?? true);
        $settings->default_login_method = $data['default_login_method'] ?? 'email';
        $settings->save();

        $this->logSettingsChange('authentication', $old, array_intersect_key($data, $old));
    }

    public function savePasswordPolicy(array $data, PasswordPolicySettings $settings): void
    {
        $old = [
            'min_length' => $settings->min_length,
            'require_uppercase' => $settings->require_uppercase,
            'require_lowercase' => $settings->require_lowercase,
            'require_number' => $settings->require_number,
            'require_special' => $settings->require_special,
            'prevent_reuse' => $settings->prevent_reuse,
            'password_history_count' => $settings->password_history_count,
            'expiry_enabled' => $settings->expiry_enabled,
            'expiry_days' => $settings->expiry_days,
        ];

        $settings->min_length = max(1, (int) ($data['min_length'] ?? 8));
        $settings->require_uppercase = (bool) ($data['require_uppercase'] ?? true);
        $settings->require_lowercase = (bool) ($data['require_lowercase'] ?? true);
        $settings->require_number = (bool) ($data['require_number'] ?? true);
        $settings->require_special = (bool) ($data['require_special'] ?? false);
        $settings->prevent_reuse = (bool) ($data['prevent_reuse'] ?? false);
        $settings->password_history_count = max(1, (int) ($data['password_history_count'] ?? 5));
        $settings->expiry_enabled = (bool) ($data['expiry_enabled'] ?? false);
        $settings->expiry_days = max(1, (int) ($data['expiry_days'] ?? 90));
        $settings->save();

        $this->logSettingsChange('password_policy', $old, array_intersect_key($data, $old));
    }

    public function saveLoginSecurity(array $data, LoginSecuritySettings $settings): void
    {
        $old = [
            'max_failed_attempts' => $settings->max_failed_attempts,
            'lockout_duration' => $settings->lockout_duration,
            'throttling_enabled' => $settings->throttling_enabled,
            'reset_throttling_enabled' => $settings->reset_throttling_enabled,
            'notify_user_on_failed' => $settings->notify_user_on_failed,
            'notify_admin_on_lock' => $settings->notify_admin_on_lock,
        ];

        $settings->max_failed_attempts = max(1, (int) ($data['max_failed_attempts'] ?? 5));
        $settings->lockout_duration = max(1, (int) ($data['lockout_duration'] ?? 15));
        $settings->throttling_enabled = (bool) ($data['throttling_enabled'] ?? true);
        $settings->reset_throttling_enabled = (bool) ($data['reset_throttling_enabled'] ?? true);
        $settings->notify_user_on_failed = (bool) ($data['notify_user_on_failed'] ?? true);
        $settings->notify_admin_on_lock = (bool) ($data['notify_admin_on_lock'] ?? false);
        $settings->save();

        $this->logSettingsChange('login_security', $old, array_intersect_key($data, $old));
    }

    public function saveSession(array $data, SessionSettings $settings): void
    {
        $old = [
            'idle_timeout' => $settings->idle_timeout,
            'allow_multiple_sessions' => $settings->allow_multiple_sessions,
            'force_logout_on_password_change' => $settings->force_logout_on_password_change,
        ];

        $settings->idle_timeout = max(1, (int) ($data['idle_timeout'] ?? 120));
        $settings->allow_multiple_sessions = (bool) ($data['allow_multiple_sessions'] ?? true);
        $settings->force_logout_on_password_change = (bool) ($data['force_logout_on_password_change'] ?? true);
        $settings->save();

        $this->logSettingsChange('session', $old, array_intersect_key($data, $old));
    }

    public function saveRegistration(array $data, RegistrationSettings $settings): void
    {
        $old = [
            'self_registration_enabled' => $settings->self_registration_enabled,
            'default_role' => $settings->default_role,
            'require_admin_approval' => $settings->require_admin_approval,
            'send_welcome_email' => $settings->send_welcome_email,
            'auto_verify_email' => $settings->auto_verify_email,
        ];

        $settings->self_registration_enabled = (bool) ($data['self_registration_enabled'] ?? false);
        $settings->default_role = $data['default_role'] ?? null;
        $settings->require_admin_approval = (bool) ($data['require_admin_approval'] ?? false);
        $settings->send_welcome_email = (bool) ($data['send_welcome_email'] ?? true);
        $settings->auto_verify_email = (bool) ($data['auto_verify_email'] ?? false);
        $settings->save();

        $this->logSettingsChange('registration', $old, array_intersect_key($data, $old));
    }

    public function saveAccountProtection(array $data, AccountProtectionSettings $settings): void
    {
        $old = [
            'disable_after_failed_attempts' => $settings->disable_after_failed_attempts,
            'auto_unlock_after' => $settings->auto_unlock_after,
            'notify_user' => $settings->notify_user,
            'notify_admin' => $settings->notify_admin,
        ];

        $settings->disable_after_failed_attempts = (bool) ($data['disable_after_failed_attempts'] ?? true);
        $settings->auto_unlock_after = max(0, (int) ($data['auto_unlock_after'] ?? 30));
        $settings->notify_user = (bool) ($data['notify_user'] ?? true);
        $settings->notify_admin = (bool) ($data['notify_admin'] ?? false);
        $settings->save();

        $this->logSettingsChange('account_protection', $old, array_intersect_key($data, $old));
    }

    private function logSettingsChange(string $page, array $old, array $new): void
    {
        $changes = [];

        foreach ($new as $field => $value) {
            if (in_array($field, self::SENSITIVE_FIELDS, true)) {
                continue;
            }

            $oldValue = $old[$field] ?? null;

            if ($oldValue !== $value) {
                $changes[$field] = ['old' => $oldValue, 'new' => $value];
            }
        }

        activity('security')
            ->causedBy(auth()->user())
            ->event('settings_updated')
            ->withProperties([
                'page' => $page,
                'changes' => $changes,
            ])
            ->log(ucwords(str_replace('_', ' ', $page)).' settings updated');
    }
}
