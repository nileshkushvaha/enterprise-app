<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Services\Security\PasswordRuleBuilder;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:rfc'],
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
            'token.required' => 'Invalid or missing reset token.',
            'email.required' => 'Email address is required.',
            'password.required' => 'Please enter your new password.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
