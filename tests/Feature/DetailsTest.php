<?php

use App\Services\InvitationDetails;

function validDetails(array $overrides = []): array
{
    return array_merge([
        'groom' => 'Yusuf', 'bride' => 'Layla',
        'groom_arabic' => 'يوسف', 'bride_arabic' => 'ليلى',
        'groom_parents' => 'Omar and Huda', 'bride_parents' => 'Karim and Noor',
        'date' => '2026-12-03', 'time' => '18:15',
        'venue' => 'Garden Hall', 'venue_address' => 'Hall No. 2',
        'map_link' => 'https://maps.app.goo.gl/example',
        'rsvp_by' => '25 November', 'intro' => 'Join our joy.', 'join_line' => 'Celebrate with us',
        'note' => 'Ladies only',
        'schedule' => [['time' => '6:15 PM', 'title' => 'Nikah'], ['time' => '', 'title' => '']],
        'music' => false,
    ], $overrides);
}

// 13
it('updates the guest page and derives the date strings in +03:00', function () {
    $inv = invitee();

    $this->actingAs(admin())->putJson('/admin/details', validDetails())
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('details.starts_at', '2026-12-03T18:15:00+03:00')
        ->assertJsonPath('details.day_name', 'Thursday')
        ->assertJsonPath('details.date_long', '3 December 2026')
        ->assertJsonPath('details.short_date', '03 . 12 . 26')
        ->assertJsonPath('details.start_time', '6:15 PM onwards')
        ->assertJsonPath('details.schedule', [['time' => '6:15 PM', 'title' => 'Nikah']]);

    $html = $this->get("/i/{$inv->token}")->assertOk()
        ->assertSee('Yusuf')->assertSee('Layla')->assertSee('يوسف')
        ->assertSee('https://www.google.com/maps?q=Garden%20Hall%2C%20Hall%20No.%202', false)
        ->assertSee('Thursday, 3 December 2026, 6:15 PM onwards')
        ->assertSee('Garden Hall')->assertSee('Hall No. 2')
        ->assertSee('https://maps.app.goo.gl/example')
        ->assertSee('Ladies only')
        ->assertSee('Kindly share your response by <b>25 November</b>', false)
        ->assertSee('Schedule of events')
        ->assertDontSee('Adhari')
        ->assertDontSee('id="music"', false)
        ->getContent();

    preg_match('#<script type="application/json" id="config">(.*?)</script>#s', $html, $m);
    $config = json_decode($m[1], true);
    expect($config['startsAt'])->toBe('2026-12-03T18:15:00+03:00')
        ->and($config['initials'])->toBe('Y&L')
        ->and($config['music'])->toBeFalse();

    expect(app(InvitationDetails::class)->all()['starts_at'])->toEndWith('+03:00');
});

it('uses the wedding defaults before anything is saved', function () {
    $inv = invitee();
    $this->get("/i/{$inv->token}")->assertOk()
        ->assertSee('https://www.google.com/maps?q=Adhari%20New%20Seasons%20Bahrain', false)
        ->assertSee('Thursday, 19 November 2026, 7:30 PM onwards')
        ->assertSee('Adhari New Seasons')
        ->assertSee('Hamza and Rahiyanath')
        ->assertSee('Kindly share your response by')
        ->assertDontSee('Schedule of events');
});

// 14
it('rejects map links that do not start with https://', function (string $link) {
    $this->actingAs(admin())->putJson('/admin/details', validDetails(['map_link' => $link]))
        ->assertStatus(422)
        ->assertJson(['ok' => false])
        ->assertJsonValidationErrors('map_link');
})->with(['http://maps.google.com/x', 'javascript:alert(1)', 'maps.google.com', 'ftp://example.com']);

it('requires the core fields and limits their length', function (string $field, mixed $value) {
    $this->actingAs(admin())->putJson('/admin/details', validDetails([$field => $value]))
        ->assertStatus(422)->assertJsonValidationErrors($field);
})->with([
    ['groom', ''], ['bride', str_repeat('x', 61)], ['date', '19/11/2026'], ['time', '7pm'], ['venue', ''],
    ['intro', str_repeat('x', 401)], ['schedule', array_fill(0, 9, ['time' => '1', 'title' => 'x'])],
]);
