<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UploadCoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cover' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'cover.required' => 'Please select an image to upload.',
            'cover.image' => 'The file must be an image.',
            'cover.mimes' => 'Accepted formats: JPEG, PNG, JPG, WebP.',
            'cover.max' => 'Image size must not exceed 4 MB.',
        ];
    }
}
