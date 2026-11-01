<?php

namespace App\Services;

/** One invitation link that can go to many guests: every reply through it is saved as its own entry. */
class SharedLink
{
    public function __construct(private Settings $settings) {}

    public function token(): string
    {
        $token = $this->settings->get('shared_link');

        if (! is_string($token) || $token === '') {
            $this->settings->set('shared_link', $token = bin2hex(random_bytes(6)));
        }

        return $token;
    }

    public function matches(string $token): bool
    {
        $saved = $this->settings->get('shared_link');

        return is_string($saved) && $saved !== '' && hash_equals($saved, $token);
    }

    public function url(): string
    {
        return route('join');
    }
}
