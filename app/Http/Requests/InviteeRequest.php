<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Adding one guest (POST) or editing one (PATCH, every field optional). */
class InviteeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $digits = preg_replace('/\D+/', '', (string) $this->input('phone'));
            $this->merge(['phone' => $digits === '' ? null : $digits]);
        }
    }

    public function rules(): array
    {
        $required = $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:120'],
            'seats' => [$this->isMethod('patch') ? 'sometimes' : 'nullable', 'integer', 'min:1', 'max:'.config('wedding.max_seats')],
            'phone' => ['nullable', 'string', 'min:6', 'max:20'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.min' => 'The WhatsApp number looks too short. Include the country code, e.g. 97333334444.',
            'phone.max' => 'The WhatsApp number looks too long.',
        ];
    }
}
