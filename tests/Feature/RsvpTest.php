<?php

use App\Models\Invitee;
use App\Models\RsvpLog;

// 3
it('saves an acceptance with the guest’s own name and the number coming', function () {
    $inv = invitee(['name' => 'Ahmed Ali', 'seats' => 4]);

    $this->postJson("/i/{$inv->token}/rsvp", ['attending' => true, 'count' => 3, 'name' => '  Ahmed Ali Hassan '])
        ->assertOk()->assertExactJson(['ok' => true]);

    $inv->refresh();
    expect($inv->status)->toBe('yes')
        ->and($inv->guests)->toBe(3)
        ->and($inv->guest_names)->toBe(['Ahmed Ali Hassan'])
        ->and($inv->replied_at)->not->toBeNull();

    $log = RsvpLog::sole();
    expect($log->invitee_id)->toBe($inv->id)
        ->and($log->status)->toBe('yes')
        ->and($log->guests)->toBe(3)
        ->and($log->guest_names)->toBe(['Ahmed Ali Hassan'])
        ->and($log->ip_hash)->toHaveLength(64);
});

it('accepts more guests than the invitation’s seats', function () {
    $inv = invitee(['seats' => 1]);

    $this->postJson("/i/{$inv->token}/rsvp", ['attending' => true, 'count' => 6, 'name' => 'Ahmed'])->assertOk();
    expect($inv->fresh())->status->toBe('yes')->guests->toBe(6);
});

it('asks only for the guest’s own name, not the others’', function () {
    $inv = invitee(['seats' => 3]);

    $this->postJson("/i/{$inv->token}/rsvp", ['attending' => true, 'count' => 2, 'name' => 'Ahmed', 'names' => ['Sara']])->assertOk();
    expect($inv->fresh())->guests->toBe(2)->guest_names->toBe(['Ahmed']);
});

// 4
it('rejects replies that break the rules with 422', function (array $payload) {
    $inv = invitee(['seats' => 3]);

    $this->postJson("/i/{$inv->token}/rsvp", $payload)
        ->assertStatus(422)
        ->assertJson(['ok' => false]);

    expect($inv->fresh()->status)->toBe('pending')
        ->and(RsvpLog::count())->toBe(0);
})->with([
    'a mistyped number of guests' => [['attending' => true, 'count' => 1000, 'name' => 'Ahmed']],
    'zero guests' => [['attending' => true, 'count' => 0, 'name' => 'Ahmed']],
    'no number of guests' => [['attending' => true, 'name' => 'Ahmed']],
    'no name' => [['attending' => true, 'count' => 1]],
    'a blank name' => [['attending' => true, 'count' => 1, 'name' => '   ']],
    'a name over 80 characters' => [['attending' => true, 'count' => 1, 'name' => str_repeat('x', 81)]],
    'no answer' => [['count' => 1]],
]);

// 5
it('saves a decline with no guests', function () {
    $inv = invitee();

    $this->postJson("/i/{$inv->token}/rsvp", ['attending' => false, 'count' => 3, 'names' => ['ignored']])->assertOk();

    $inv->refresh();
    expect($inv->status)->toBe('no')
        ->and($inv->guests)->toBe(0)
        ->and($inv->guest_names)->toBeNull();
    expect(RsvpLog::sole()->status)->toBe('no');
});

// 6
it('lets a guest change their reply from the same link', function () {
    $inv = invitee(['seats' => 2]);

    $this->postJson("/i/{$inv->token}/rsvp", ['attending' => true, 'count' => 2, 'name' => 'Ahmed'])->assertOk();
    $this->postJson("/i/{$inv->token}/rsvp", ['attending' => false])->assertOk();

    expect(Invitee::count())->toBe(1)
        ->and($inv->fresh()->status)->toBe('no')
        ->and(RsvpLog::where('invitee_id', $inv->id)->pluck('status')->all())->toBe(['yes', 'no']);
});

it('returns 404 for a reply to an unknown link', function () {
    $this->postJson('/i/abcdef123456/rsvp', ['attending' => false])->assertNotFound()->assertJson(['ok' => false]);
});

it('limits replies to 10 a minute per link', function () {
    $inv = invitee();
    for ($i = 0; $i < 10; $i++) {
        $this->postJson("/i/{$inv->token}/rsvp", ['attending' => false])->assertOk();
    }
    $this->postJson("/i/{$inv->token}/rsvp", ['attending' => false])->assertStatus(429)->assertJson(['ok' => false]);
});

it('shows the previous reply to a returning guest', function () {
    $inv = invitee(['seats' => 4, 'status' => 'yes', 'guests' => 2, 'guest_names' => ['Ahmed Ali', 'Sara Ahmed']]);

    $html = $this->get("/i/{$inv->token}")->getContent();
    preg_match('#<script type="application/json" id="config">(.*?)</script>#s', $html, $m);

    expect(json_decode($m[1], true)['invitee'])->toBe([
        'name' => 'Ahmed Ali', 'seats' => 4, 'status' => 'yes', 'guests' => 2, 'guest_names' => ['Ahmed Ali', 'Sara Ahmed'],
    ]);
});

it('lets the couple preview the invitation without a token or counting an open', function () {
    $inv = invitee();
    $html = $this->actingAs(admin())->get('/admin/preview')->assertOk()->assertDontSee('Dear ', false)->getContent();

    preg_match('#<script type="application/json" id="config">(.*?)</script>#s', $html, $m);
    $config = json_decode($m[1], true);
    expect($config['preview'])->toBeTrue()
        ->and($config['invitee'])->toBeNull()
        ->and($config['rsvpUrl'])->toBe(route('admin.preview.rsvp'))
        ->and($inv->fresh()->open_count)->toBe(0);
});

it('limits replies from one visitor across many links, so links can’t be guessed', function () {
    for ($i = 0; $i < 20; $i++) {
        $this->postJson('/i/'.bin2hex(random_bytes(6)).'/rsvp', ['attending' => false])->assertNotFound();
    }
    $this->postJson('/i/'.bin2hex(random_bytes(6)).'/rsvp', ['attending' => false])->assertStatus(429);
});

it('shows Coming and Declined replies correctly on the dashboard, from personal and shared links', function () {
    $coming = invitee(['name' => 'Coming Guest', 'seats' => 4]);
    $declined = invitee(['name' => 'Declined Guest', 'seats' => 2]);
    $waiting = invitee(['name' => 'Waiting Guest', 'seats' => 1]);

    $this->postJson("/i/{$coming->token}/rsvp", ['attending' => true, 'count' => 3, 'name' => 'Coming Guest'])->assertOk();
    $this->postJson("/i/{$declined->token}/rsvp", ['attending' => false])->assertOk();
    $this->postJson('/join', ['attending' => true, 'count' => 2, 'name' => 'Via Shared Link'])->assertOk();
    $this->postJson('/join', ['attending' => false])->assertOk();

    $this->actingAs(admin());
    $this->getJson('/admin/stats')->assertOk()->assertJsonPath('stats.accepted', 2)
        ->assertJsonPath('stats.declined', 2)->assertJsonPath('stats.waiting', 1)
        ->assertJsonPath('stats.guests_coming', 5);

    $rows = collect($this->getJson('/admin/invitees')->json('invitees'))->keyBy('name');
    expect($rows['Coming Guest']['status'])->toBe('yes')->and($rows['Coming Guest']['guests'])->toBe(3)
        ->and($rows['Declined Guest']['status'])->toBe('no')->and($rows['Declined Guest']['guests'])->toBe(0)
        ->and($rows['Waiting Guest']['status'])->toBe('pending')
        ->and($rows['Via Shared Link']['status'])->toBe('yes');

    // Changing their mind from Coming to Declined updates the totals.
    $this->postJson("/i/{$coming->token}/rsvp", ['attending' => false])->assertOk();
    $this->getJson('/admin/stats')->assertJsonPath('stats.accepted', 1)->assertJsonPath('stats.guests_coming', 2);
});

it('opens the one shared link for everyone, without a personal token, and keeps it private from search engines', function () {
    $res = $this->get('/join')->assertOk();

    expect($res->headers->get('X-Robots-Tag'))->toContain('noindex');
    $res->assertSee('rsvp', false);
    expect(Invitee::count())->toBe(0); // just looking at the page creates no guest
});
