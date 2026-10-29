<?php

use App\Models\Invitee;
use Illuminate\Http\UploadedFile;

beforeEach(fn () => $this->actingAs(admin()));

// 10
it('adds one guest with a unique token and a digits-only phone', function () {
    $this->postJson('/admin/invitees', ['name' => 'Ahmed Ali & family', 'seats' => 4, 'phone' => '+973 3333-4444'])
        ->assertCreated()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('invitee.name', 'Ahmed Ali & family')
        ->assertJsonPath('invitee.seats', 4)
        ->assertJsonPath('invitee.phone', '97333334444');

    $inv = Invitee::sole();
    expect($inv->token)->toMatch('/^[a-f0-9]{12}$/')
        ->and($inv->status)->toBe('pending');
});

it('defaults a new guest to one seat and rejects bad input', function () {
    $this->postJson('/admin/invitees', ['name' => 'Rahul Nair'])->assertCreated()->assertJsonPath('invitee.seats', 1);
    $this->postJson('/admin/invitees', ['name' => ''])->assertStatus(422)->assertJson(['ok' => false]);
    $this->postJson('/admin/invitees', ['name' => 'X', 'seats' => 21])->assertStatus(422);
    $this->postJson('/admin/invitees', ['name' => 'X', 'phone' => '12'])->assertStatus(422);
    expect(Invitee::count())->toBe(1);
});

it('bulk-adds lines, reporting the bad ones without saving them', function () {
    $text = implode("\n", [
        'Ahmed Ali, 3, +973 3333 4444',
        'Fatima Rashid, 2',
        'Rahul Nair',
        '97355556666, Shameer K, 5',    // columns out of order: "Shameer K" is not a number
        ', 2',                          // no name
        'Big Family, 45',               // too many seats
        'Odd One, hello',               // not a number
        '',
        'Aisha Kareem, 97312345678, 1',
    ]);

    $res = $this->postJson('/admin/invitees/bulk', ['text' => $text])->assertCreated()->json();

    $byName = Invitee::all()->keyBy('name');
    expect($byName['Ahmed Ali']->seats)->toBe(3)
        ->and($byName['Ahmed Ali']->phone)->toBe('97333334444')
        ->and($byName['Fatima Rashid']->seats)->toBe(2)
        ->and($byName['Fatima Rashid']->phone)->toBeNull()
        ->and($byName['Rahul Nair']->seats)->toBe(1)
        ->and($byName['Aisha Kareem']->seats)->toBe(1)
        ->and($byName['Aisha Kareem']->phone)->toBe('97312345678')
        ->and($byName->has('Big Family'))->toBeFalse()
        ->and($byName->has('Odd One'))->toBeFalse();

    $tokens = Invitee::pluck('token');
    expect($tokens->unique()->count())->toBe($tokens->count())
        ->and($tokens->every(fn ($t) => preg_match('/^[a-f0-9]{12}$/', $t)))->toBeTrue();

    $skipped = collect($res['skipped'])->keyBy('line');
    expect($res['added'])->toBe(Invitee::count())
        ->and($skipped->keys()->all())->toBe([4, 5, 6, 7])
        ->and($skipped[5]['reason'])->toContain('name is missing')
        ->and($skipped[6]['reason'])->toContain('between 1 and 20')
        ->and($skipped[7]['reason'])->toContain('not a number');
});

it('bulk-adds from an uploaded CSV with a header row', function () {
    $csv = "name,seats,phone\n\"Ahmed Ali & family\",3,97333334444\nFatima Rashid,2,\n";
    $file = UploadedFile::fake()->createWithContent('guests.csv', $csv);

    $this->post('/admin/invitees/bulk', ['file' => $file], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('added', 2)
        ->assertJsonPath('skipped', []);

    expect(Invitee::where('name', 'Ahmed Ali & family')->value('seats'))->toBe(3);
});

it('edits a guest and keeps their confirmed number of guests', function () {
    $inv = invitee(['seats' => 4, 'status' => 'yes', 'guests' => 4, 'guest_names' => ['A', 'B', 'C', 'D']]);

    $this->patchJson("/admin/invitees/{$inv->id}", ['name' => 'New Name', 'seats' => 2, 'phone' => '973 1111 2222', 'notes' => 'Table 4'])
        ->assertOk()
        ->assertJsonPath('invitee.name', 'New Name')
        ->assertJsonPath('invitee.phone', '97311112222')
        ->assertJsonPath('invitee.notes', 'Table 4');

    $inv->refresh();
    expect($inv->seats)->toBe(2)->and($inv->guests)->toBe(4);
});

it('clears a reply', function () {
    $inv = invitee(['status' => 'no', 'replied_at' => now()]);

    $this->postJson("/admin/invitees/{$inv->id}/reset")->assertOk()->assertJsonPath('invitee.status', 'pending');
    expect($inv->fresh()->replied_at)->toBeNull();
});

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
