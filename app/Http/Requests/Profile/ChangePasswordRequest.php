<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password'         => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required'         => 'Please enter your current password.',
            'current_password.current_password'  => 'Your current password is incorrect.',
            'password.required'                  => 'Please enter a new password.',
            'password.confirmed'                 => 'Password confirmation does not match.',
            'password.min'                       => 'Password must be at least 8 characters.',
        ];
    }
}
