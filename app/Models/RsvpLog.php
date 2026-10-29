<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsvpLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['status', 'guests', 'guest_names', 'ip_hash', 'user_agent'];

    protected function casts(): array
    {
        return [
            'guests' => 'integer',
            'guest_names' => 'array',
        ];
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(Invitee::class);
    }
}
