<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invitee extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'seats', 'notes'];

    protected $attributes = [
        'seats' => 1,
        'status' => 'pending',
        'guests' => 0,
        'open_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'guests' => 'integer',
            'open_count' => 'integer',
            'guest_names' => 'array',
            'replied_at' => 'datetime',
            'opened_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invitee $invitee) {
            $invitee->token ??= static::newToken();
        });
    }

    /** 12 hex characters from random_bytes, unique across invitees. */
    public static function newToken(): string
    {
        do {
            $token = bin2hex(random_bytes(6));
        } while (static::where('token', $token)->exists());

        return $token;
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token', $token)->first();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RsvpLog::class);
    }

    public function scopeComing(Builder $query): void
    {
        $query->where('status', 'yes');
    }

    public function scopeDeclined(Builder $query): void
    {
        $query->where('status', 'no');
    }

    public function scopeWaiting(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    public function link(): string
    {
        return route('invite', $this->token);
    }

    public function regenerateToken(): void
    {
        $this->token = static::newToken();
        $this->save();
    }

    public function clearReply(): void
    {
        $this->forceFill(['status' => 'pending', 'guests' => 0, 'guest_names' => null, 'replied_at' => null])->save();
    }

    /** The only data a guest page ever receives about its invitee. */
    public function toGuestArray(): array
    {
        return [
            'name' => $this->name,
            'seats' => $this->seats,
            'status' => $this->status,
            'guests' => $this->guests,
            'guest_names' => $this->guest_names ?? [],
        ];
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'link' => $this->link(),
            'name' => $this->name,
            'phone' => $this->phone,
            'seats' => $this->seats,
            'status' => $this->status,
            'guests' => $this->guests,
            'guest_names' => $this->guest_names ?? [],
            'notes' => $this->notes,
            'replied_at' => $this->replied_at?->toIso8601String(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'open_count' => $this->open_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
