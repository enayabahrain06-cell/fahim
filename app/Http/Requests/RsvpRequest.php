<?php

namespace App\Http\Requests;

use App\Models\Invitee;
use Illuminate\Foundation\Http\FormRequest;

class RsvpRequest extends FormRequest
{
    private ?Invitee $invitee = null;

    /** The invitee behind the link; an unknown token is a plain 404. */
    public function invitee(): Invitee
    {
        return $this->invitee ??= Invitee::findByToken((string) $this->route('token')) ?? abort(404);
    }

    public function authorize(): bool
    {
        return (bool) $this->invitee();
    }

    public function rules(): array
    {
        return [
            'attending' => ['required', 'boolean'],
            'count' => ['exclude_if:attending,false', 'required', 'integer', 'min:1', 'max:'.config('wedding.max_guests')],
            'name' => ['exclude_if:attending,false', 'required', 'string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function messages(): array
    {
        return [
            'count.max' => 'Please enter up to :max guests.',
            'name.required' => 'Please enter your name.',
        ];
    }

    public function attending(): bool
    {
        return $this->boolean('attending');
    }

    /** How many are coming, the invitee included. Only the number is asked, not the guests' names. */
    public function guestCount(): int
    {
        return $this->attending() ? (int) $this->validated('count') : 0;
    }

    /** @return list<string>|null the name the guest typed when accepting (theirs only), or null for a decline */
    public function replyNames(): ?array
    {
        return $this->attending() ? [$this->validated('name')] : null;
    }
}
