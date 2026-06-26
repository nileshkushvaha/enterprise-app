<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['nullable', 'string', 'max:100'],
            'email'         => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone'         => ['nullable', 'string', 'max:20'],
            'gender'        => ['nullable', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'address'       => ['nullable', 'string', 'max:500'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'country_id'    => ['nullable', 'integer', 'exists:countries,id'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'timezone'      => ['nullable', 'string', 'timezone:all'],
            'language'      => ['nullable', 'string', 'max:10'],
            'date_format'   => ['nullable', Rule::in(['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y'])],
            'time_format'   => ['nullable', Rule::in(['H:i', 'h:i A'])],
            'theme'         => ['nullable', Rule::in(['dark', 'light', 'system'])],
            'email_notifications'    => ['nullable', 'boolean'],
            'system_notifications'   => ['nullable', 'boolean'],
            'marketing_emails'       => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required'  => 'First name is required.',
            'email.required'       => 'Email address is required.',
            'email.unique'         => 'This email address is already taken.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'timezone.timezone'    => 'Please select a valid timezone.',
            'country_id.exists'    => 'Please select a valid country.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email_notifications'  => $this->boolean('email_notifications'),
            'system_notifications' => $this->boolean('system_notifications'),
            'marketing_emails'     => $this->boolean('marketing_emails'),
        ]);
    }
}
