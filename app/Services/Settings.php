<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/** Key-value settings stored as JSON, cached as one map and forgotten on every write. */
class Settings
{
    private const CACHE_KEY = 'settings.all';

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        if ($value === null) {
            Setting::whereKey($key)->delete();
        } else {
            Setting::updateOrCreate(['key' => $key], ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function forget(string $key): void
    {
        $this->set($key, null);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::all()
            ->mapWithKeys(fn (Setting $s) => [$s->key => json_decode((string) $s->value, true)])
            ->all());
    }
}
