<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // `mimetypes` checks the real content type, not the file name.
            'photo' => ['required', 'file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'No photo was received.',
            'photo.max' => 'The photo is too large (8 MB max).',
            'photo.mimetypes' => 'Please choose a JPG, PNG or WebP image.',
        ];
    }
}
