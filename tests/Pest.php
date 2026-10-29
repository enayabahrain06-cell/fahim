<?php

use App\Models\Invitee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

function admin(): User
{
    return User::factory()->create();
}

function invitee(array $attributes = []): Invitee
{
    $invitee = Invitee::create(array_merge(['name' => 'Ahmed Ali', 'seats' => 3], array_intersect_key($attributes, array_flip(['name', 'seats', 'phone', 'notes']))));
    $extra = array_diff_key($attributes, array_flip(['name', 'seats', 'phone', 'notes']));
    if ($extra) {
        $invitee->forceFill($extra)->save();
    }

    return $invitee->fresh();
}
