<?php

use App\Models\Invitee;
use Illuminate\Http\UploadedFile;

beforeEach(fn () => $this->actingAs(admin()));

it('answers unknown guests with a JSON 404', function () {
    $this->deleteJson('/admin/invitees/999')->assertNotFound()->assertJson(['ok' => false]);
});

// 16
it('returns correct stats for a seeded guest list', function () {
    invitee(['name' => 'A', 'seats' => 4, 'status' => 'yes', 'guests' => 3]);
    invitee(['name' => 'B', 'seats' => 2, 'status' => 'yes', 'guests' => 2]);
    invitee(['name' => 'C', 'seats' => 4, 'status' => 'yes', 'guests' => 4]);
    invitee(['name' => 'D', 'seats' => 1, 'status' => 'no']);
    invitee(['name' => 'E', 'seats' => 1]);
    invitee(['name' => 'F', 'seats' => 5]);

    $this->getJson('/admin/stats')->assertOk()->assertJson(['ok' => true, 'stats' => [
        'invitations' => 6,
        'accepted' => 3,
        'declined' => 1,
        'waiting' => 2,
        'answered' => 4,
        'guests_coming' => 9,
        'total_seats' => 17,
        'acceptance_pct' => 75,   // 3 of 4 answered
        'seats_pct' => 53,        // 9 of 17 seats
    ]]);

    $this->getJson('/admin/invitees')->assertOk()->assertJsonCount(6, 'invitees');
});

it('returns zeros for an empty guest list', function () {
    $this->getJson('/admin/stats')->assertJsonPath('stats.acceptance_pct', 0)->assertJsonPath('stats.seats_pct', 0);
});

it('removes several selected guests at once and leaves the rest', function () {
    $a = invitee(['name' => 'A']);
    $b = invitee(['name' => 'B']);
    $c = invitee(['name' => 'C']);

    $this->deleteJson('/admin/invitees', ['ids' => [$a->id, $b->id, 99999]])
        ->assertOk()->assertJson(['ok' => true, 'removed' => 2]);

    expect(Invitee::pluck('name')->all())->toBe(['C']);
});

it('rejects a bulk delete without ids', function () {
    $this->deleteJson('/admin/invitees', ['ids' => []])->assertUnprocessable();
    $this->deleteJson('/admin/invitees')->assertUnprocessable();
});
