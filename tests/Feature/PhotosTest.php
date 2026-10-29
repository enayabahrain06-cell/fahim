<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(admin());
});

// 15
it('rejects a file that is not an image', function () {
    $file = UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "hi";');

    $this->post('/admin/photos/couple', ['photo' => $file], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', fn ($e) => str_contains($e, 'Please choose a JPG, PNG or WebP image.'));
    Storage::disk('public')->assertMissing('invitation/couple.jpg');
});

it('rejects an image over 8 MB', function () {
    $file = UploadedFile::fake()->image('big.jpg', 100, 100)->size(8193);

    $this->post('/admin/photos/couple', ['photo' => $file], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJson(['ok' => false, 'error' => 'The photo is too large (8 MB max).']);
});

it('rejects an unknown slot', function () {
    $this->post('/admin/photos/cover', ['photo' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
        ->assertNotFound();
});

it('replaces the bundled artwork with an upload, and restores it on removal', function () {
    $inv = invitee();
    $this->get("/i/{$inv->token}")->assertSee('couple-default', false);

    $res = $this->post('/admin/photos/couple', ['photo' => UploadedFile::fake()->image('us.png', 2400, 1800)], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('ok', true);

    Storage::disk('public')->assertExists('invitation/couple.jpg');
    [$w, $h, $type] = getimagesizefromstring(Storage::disk('public')->get('invitation/couple.jpg'));
    expect(max($w, $h))->toBe(1600)
        ->and($type)->toBe(IMAGETYPE_JPEG)
        ->and($res->json('photos.couple'))->toContain('invitation/couple.jpg?v=');

    $this->get("/i/{$inv->token}")
        ->assertSee('invitation/couple.jpg?v=', false)
        ->assertDontSee('couple-default', false);

    $this->deleteJson('/admin/photos/couple')->assertOk()->assertJsonPath('photos.couple', null);
    Storage::disk('public')->assertMissing('invitation/couple.jpg');
    $this->get("/i/{$inv->token}")->assertSee('couple-default', false);
});

it('shows the uploaded venue photo and the map in the venue card', function () {
    $inv = invitee();
    $this->post('/admin/photos/venue', ['photo' => UploadedFile::fake()->image('hall.webp', 800, 600)], ['Accept' => 'application/json'])->assertOk();

    $this->get("/i/{$inv->token}")
        ->assertSee('invitation/venue.jpg?v=', false)
        ->assertSee('<iframe src="https://www.google.com/maps?q=', false)
        ->assertSee('https://calendar.google.com/calendar/render?action=TEMPLATE', false);
});
