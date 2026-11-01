<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MusicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // `mimetypes` checks the real content type, not the file name.
            'music' => ['required', 'file', 'mimetypes:audio/mpeg,audio/mp3,audio/mp4,audio/x-m4a,audio/aac,audio/ogg,audio/wav,audio/x-wav,audio/webm,video/mp4'],
        ];
    }

    public function messages(): array
    {
        return [
            'music.required' => 'No music file was received.',
            'music.mimetypes' => 'Please choose an MP3, M4A, WAV or OGG audio file.',
        ];
    }
}
