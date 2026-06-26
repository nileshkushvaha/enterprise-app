<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'Please select an image to upload.',
            'avatar.image'    => 'The file must be an image.',
            'avatar.mimes'    => 'Accepted formats: JPEG, PNG, JPG, WebP.',
            'avatar.max'      => 'Image size must not exceed 2 MB.',
        ];
    }
}
