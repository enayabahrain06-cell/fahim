<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'user_name', 'action', 'detail', 'ip', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** Write one line to the admin activity log. Never lets logging break a request. */
    public static function record(string $action, ?string $detail = null, ?User $user = null, ?string $fallbackName = null): void
    {
        try {
            static::create([
                'user_id' => $user?->id,
                'user_name' => $user ? ($user->username ?: $user->name) : $fallbackName,
                'action' => $action,
                'detail' => $detail !== null ? mb_substr($detail, 0, 250) : null,
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
