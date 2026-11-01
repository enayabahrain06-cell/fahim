<?php

use App\Models\Invitee;

// 1
it('shows the private notice with 404 and no details beyond the names', function (string $url) {
    $this->get($url)
        ->assertNotFound()
        ->assertSee('This invitation is private')
        ->assertSee('Fahim')
        ->assertSee('Marwa')
        ->assertDontSee('Adhari')
        ->assertDontSee('Hall No. 1')
        ->assertDontSee('November')
        ->assertDontSee('2026')
        ->assertDontSee('Rahiyanath');
})->with(['/', '/i/abcdef123456', '/i/not-a-token', '/something-else']);

// 2
it('opens a valid invitation without counting opens', function () {
    $inv = invitee(['name' => 'Fatima Rashid']);

    $this->get("/i/{$inv->token}")->assertOk()->assertDontSee('Dear ', false);
    $this->get("/i/{$inv->token}")->assertOk();

    expect($inv->fresh()->open_count)->toBe(0)->and($inv->fresh()->opened_at)->toBeNull();
});

it('gives the guest page only that invitee’s own data', function () {
    $inv = invitee(['name' => 'Fatima Rashid', 'phone' => '97311112222', 'notes' => 'Bride’s aunt']);
    $other = invitee(['name' => 'Someone Else']);

    $html = $this->get("/i/{$inv->token}")->assertOk()->getContent();
    preg_match('#<script type="application/json" id="config">(.*?)</script>#s', $html, $m);
    $config = json_decode($m[1], true);

    expect(array_keys($config['invitee']))->toBe(['name', 'seats', 'status', 'guests', 'guest_names'])
        ->and($html)->not->toContain('97311112222')
        ->and($html)->not->toContain('Bride’s aunt')
        ->and($html)->not->toContain('Someone Else')
        ->and($html)->not->toContain($other->token);
});

// 12
it('stops the link working and drops the guest from stats after removal', function () {
    $inv = invitee(['seats' => 4, 'status' => 'yes', 'guests' => 3]);
    $admin = admin();

    $this->actingAs($admin)->getJson('/admin/stats')->assertJsonPath('stats.guests_coming', 3);
    $this->actingAs($admin)->deleteJson("/admin/invitees/{$inv->id}")->assertOk()->assertJson(['ok' => true]);

    expect(Invitee::count())->toBe(0);
    $this->get("/i/{$inv->token}")->assertNotFound();
    $this->actingAs($admin)->getJson('/admin/stats')
        ->assertJsonPath('stats.guests_coming', 0)
        ->assertJsonPath('stats.total_seats', 0)
        ->assertJsonPath('stats.invitations', 0);
});

// 18
it('downloads a calendar event with the right UTC start and 4 hours long', function () {
    $inv = invitee();

    $res = $this->get("/i/{$inv->token}/calendar.ics")->assertOk();
    $ics = $res->getContent();

    expect($res->headers->get('Content-Type'))->toStartWith('text/calendar')
        ->and($ics)->toContain("BEGIN:VCALENDAR\r\n")
        ->and($ics)->toContain("BEGIN:VEVENT\r\n")
        ->and($ics)->toContain("DTSTART:20261119T163000Z\r\n")   // 7:30 PM in Bahrain (+03:00)
        ->and($ics)->toContain("DTEND:20261119T203000Z\r\n")
        ->and($ics)->toContain('SUMMARY:Fahim & Marwa Wedding')
        ->and($ics)->toContain('LOCATION:Adhari New Seasons\, Hall No. 1')
        ->and($ics)->toContain("END:VEVENT\r\nEND:VCALENDAR\r\n");

    $this->get('/i/abcdef123456/calendar.ics')->assertNotFound();
});

// 19
it('sends the security headers and noindex on every response', function (string $method, string $url, bool $signedIn) {
    $inv = invitee();
    if ($signedIn) {
        $this->actingAs(admin());
    }
    $res = $this->json($method, str_replace('{token}', $inv->token, $url));

    $res->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $csp = $res->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'")
        ->toContain('https://fonts.googleapis.com')
        ->toContain('https://fonts.gstatic.com')
        ->toContain("frame-ancestors 'none'");
})->with([
    ['GET', '/', false],
    ['GET', '/i/{token}', false],
    ['GET', '/i/{token}/calendar.ics', false],
    ['POST', '/i/{token}/rsvp', false],
    ['GET', '/admin/login', false],
    ['GET', '/admin/stats', false],
    ['GET', '/admin/stats', true],
    ['GET', '/admin', true],
]);

it('marks guest pages noindex and serves a disallow-all robots.txt', function () {
    $inv = invitee();

    $this->get("/i/{$inv->token}")->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    $this->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    expect(file_get_contents(public_path('robots.txt')))->toContain('Disallow: /');
});
