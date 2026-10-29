<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:10', 'max:200', 'confirmed'],
        ];
    }
}
