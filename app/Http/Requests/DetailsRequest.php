<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'groom' => ['required', 'string', 'max:60'],
            'bride' => ['required', 'string', 'max:60'],
            'groom_arabic' => ['nullable', 'string', 'max:60'],
            'bride_arabic' => ['nullable', 'string', 'max:60'],
            'groom_parents' => ['nullable', 'string', 'max:120'],
            'bride_parents' => ['nullable', 'string', 'max:120'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'venue' => ['required', 'string', 'max:120'],
            'venue_address' => ['nullable', 'string', 'max:160'],
            'map_link' => ['nullable', 'string', 'max:600', 'url:https', 'starts_with:https://'],
            'rsvp_by' => ['nullable', 'string', 'max:60'],
            'intro' => ['nullable', 'string', 'max:400'],
            'join_line' => ['nullable', 'string', 'max:120'],
            'verse' => ['nullable', 'string', 'max:300'],
            'welcome' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:120'],
            'share_title' => ['nullable', 'string', 'max:80'],
            'share_description' => ['nullable', 'string', 'max:200'],
            'share_message' => ['nullable', 'string', 'max:500'],
            'schedule' => ['nullable', 'array', 'max:8'],
            'schedule.*.time' => ['nullable', 'string', 'max:20'],
            'schedule.*.title' => ['nullable', 'string', 'max:80'],
            'music' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'groom' => 'groom name',
            'bride' => 'bride name',
            'map_link' => 'Google Maps link',
            'rsvp_by' => 'reply by',
            'intro' => 'invitation text',
            'verse' => 'verse',
            'schedule' => 'schedule of events',
        ];
    }

    public function messages(): array
    {
        return [
            'map_link.starts_with' => 'The map link must start with https://',
            'map_link.url' => 'The map link must be a full https:// address.',
            'schedule.max' => 'The schedule can have up to 8 moments.',
        ];
    }
}
