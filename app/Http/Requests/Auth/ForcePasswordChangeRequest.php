<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Services\Auth\PasswordLifecycleService;
use App\Services\Security\PasswordRuleBuilder;
use Illuminate\Foundation\Http\FormRequest;

class ForcePasswordChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(PasswordLifecycleService::class)->mustChange($user);
    }

    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'confirmed',
                app(PasswordRuleBuilder::class)->build(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Please enter a new password.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
