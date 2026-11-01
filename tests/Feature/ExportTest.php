<?php

use App\Models\Invitee;
use App\Services\GuestStats;
use App\Services\InvitationDetails;
use App\Services\PdfText;
use ArPHP\I18N\Arabic;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->actingAs(admin());
    invitee(['name' => 'Ahmed Ali', 'seats' => 4, 'status' => 'yes', 'guests' => 2, 'guest_names' => ['Ahmed Ali', 'سارة أحمد'], 'replied_at' => now()]);
    invitee(['name' => 'Mohammed Saleem', 'seats' => 1, 'status' => 'no', 'phone' => '97311112222']);
    invitee(['name' => 'عائشة كريم', 'seats' => 2]);
});

// 17
it('downloads the guest list as a PDF with the Arabic font embedded', function () {
    Carbon::setTestNow('2026-10-18 09:00:00');

    $res = $this->get('/admin/export/pdf')->assertOk();

    expect($res->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($res->headers->get('Content-Disposition'))->toContain('Fahim-Marwa-guest-list-2026-10-18.pdf')
        ->and($res->getContent())->toStartWith('%PDF')
        ->and($res->getContent())->toContain('Amiri');   // the font with Arabic glyphs is embedded
});

it('lays out the Coming and Declined sections, sorted by name, with shaped Arabic names', function () {
    $details = app(InvitationDetails::class);
    $all = Invitee::all()->sortBy('name')->values();
    $html = view('exports.guest-list', [
        'd' => $details->all(), 'couple' => $details->couple(), 'stats' => app(GuestStats::class)->summary(),
        'coming' => $all->where('status', 'yes')->values(), 'declined' => $all->where('status', 'no')->values(),
        'waiting' => $all->where('status', 'pending')->values(),
        'generated' => now(), 'tz' => 'Asia/Bahrain', 't' => app(PdfText::class),
    ])->render();

    $shaped = (new Arabic)->utf8Glyphs('سارة أحمد');

    expect($html)->toContain('Fahim &amp; Marwa · Guest list')
        ->toContain('<h2>Coming')->toContain('<h2>Declined')->not->toContain('No reply yet')
        ->toContain('Mohammed Saleem')->toContain('97311112222')
        ->toContain('class="ar"')->toContain(e($shaped))
        ->toContain('font-family: "Amiri"');

    expect(strpos($html, '<h2>Coming'))->toBeLessThan(strpos($html, 'Ahmed Ali'))
        ->and(strpos($html, '<h2>Declined'))->toBeLessThan(strpos($html, 'Mohammed Saleem'))
        ->and(strpos($html, '<h2>Coming'))->toBeLessThan(strpos($html, e($shaped)));
});

it('exports a CSV with every column and the link', function () {
    $res = $this->get('/admin/export/csv')->assertOk();
    $csv = $res->streamedContent();

    expect($res->headers->get('Content-Type'))->toContain('text/csv')
        ->and($csv)->toStartWith("\xEF\xBB\xBFid,name,phone,seats,status,guests,guest_names,replied_at,opened_at,open_count,notes,token,link,created_at")
        ->and($csv)->toContain('عائشة كريم')
        ->and($csv)->toContain(url('/i/'.Invitee::where('name', 'Ahmed Ali')->value('token')));
});

it('neutralises spreadsheet formulas in the CSV', function () {
    invitee(['name' => '=HYPERLINK("http://evil")']);

    expect($this->get('/admin/export/csv')->streamedContent())->toContain("'=HYPERLINK");
});
