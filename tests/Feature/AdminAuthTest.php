<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

// 7
it('sends the first visitor to setup, then hides setup once the admin exists', function () {
    $this->get('/admin')->assertRedirect('/admin/setup');
    $this->get('/admin/login')->assertRedirect('/admin/setup');
    $this->get('/admin/setup')->assertOk()->assertSee('Create your account');

    $this->post('/admin/setup', [
        'name' => 'Fahim', 'email' => 'fahim@example.com',
        'password' => 'a-long-password', 'password_confirmation' => 'a-long-password',
    ])->assertRedirect('/admin');

    $this->assertAuthenticated();
    expect(User::count())->toBe(1)
        ->and(Hash::check('a-long-password', User::sole()->password))->toBeTrue();

    $this->get('/admin/setup')->assertNotFound();
    $this->post('/admin/setup', [
        'name' => 'Intruder', 'email' => 'x@example.com',
        'password' => 'another-password', 'password_confirmation' => 'another-password',
    ])->assertNotFound();
    expect(User::count())->toBe(1);
});

it('requires a confirmed password of at least 10 characters at setup', function () {
    $this->post('/admin/setup', ['name' => 'F', 'email' => 'f@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');
    $this->post('/admin/setup', ['name' => 'F', 'email' => 'f@example.com', 'password' => 'a-long-password', 'password_confirmation' => 'different-one'])
        ->assertSessionHasErrors('password');
    expect(User::count())->toBe(0);
});

// 8
it('keeps admin pages behind sign-in', function (string $method, string $url) {
    admin();

    $this->json($method, $url)->assertUnauthorized()->assertJson(['ok' => false]);
    if ($method === 'GET') {
        $this->get($url)->assertRedirect('/admin/login');
    }
})->with([
    ['GET', '/admin'],
    ['GET', '/admin/preview'],
    ['GET', '/admin/stats'],
    ['GET', '/admin/invitees'],
    ['POST', '/admin/invitees'],
    ['POST', '/admin/invitees/bulk'],
    ['PATCH', '/admin/invitees/1'],
    ['POST', '/admin/invitees/1/reset'],
    ['POST', '/admin/invitees/1/regenerate'],
    ['DELETE', '/admin/invitees/1'],
    ['PUT', '/admin/details'],
    ['POST', '/admin/photos/couple'],
    ['DELETE', '/admin/photos/venue'],
    ['GET', '/admin/export/pdf'],
    ['GET', '/admin/export/csv'],
]);

it('signs the couple in and out', function () {
    $user = User::factory()->create(['email' => 'couple@example.com', 'password' => 'a-long-password']);

    $this->post('/admin/login', ['email' => 'couple@example.com', 'password' => 'a-long-password'])->assertRedirect('/admin');
    $this->assertAuthenticatedAs($user);
    $this->get('/admin')->assertOk()->assertSee('Wedding RSVPs');

    $this->post('/admin/logout')->assertRedirect('/admin/login');
    $this->assertGuest();
});

// 9
it('throttles sign-in after 5 failed attempts', function () {
    User::factory()->create(['email' => 'couple@example.com', 'password' => 'a-long-password']);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/admin/login', ['email' => 'couple@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
    }
    $this->post('/admin/login', ['email' => 'couple@example.com', 'password' => 'a-long-password'])->assertStatus(429);
    $this->assertGuest();
});

it('creates or resets the admin from the command line', function () {
    $this->artisan('wedding:admin', ['--name' => 'Fahim', '--email' => 'fahim@example.com', '--password' => 'first-password'])
        ->assertSuccessful();
    $this->artisan('wedding:admin', ['--email' => 'new@example.com', '--password' => 'second-password'])
        ->assertSuccessful();

    $user = User::sole();
    expect($user->email)->toBe('new@example.com')
        ->and(Hash::check('second-password', $user->password))->toBeTrue();

    $this->artisan('wedding:admin', ['--email' => 'new@example.com', '--password' => 'short'])->assertFailed();
});

it('backs up the database and keeps the newest copies', function () {
    $dir = storage_path('app/backups');
    $db = tempnam(sys_get_temp_dir(), 'wedding').'.sqlite';
    touch($db);
    config(['database.connections.sqlite.database' => $db]);
    File::ensureDirectoryExists($dir);
    $before = glob($dir.'/wedding-*.sqlite');
    for ($i = 0; $i < 16; $i++) {
        touch(sprintf('%s/wedding-2000-01-%02d-000000.sqlite', $dir, $i + 1));
    }

    $this->artisan('wedding:backup')->assertSuccessful();

    $after = glob($dir.'/wedding-*.sqlite');
    expect(count($after))->toBe(14)
        ->and(collect($after)->contains(fn ($f) => str_contains($f, now()->timezone(config('wedding.timezone'))->format('Y-m-d'))))->toBeTrue();

    array_map('unlink', glob($dir.'/wedding-*.sqlite'));
    foreach ($before as $f) {
        touch($f);
    }
    @unlink($db);
});

it('lets a signed-in user add, edit and delete other users, but not themselves', function () {
    $me = User::factory()->create();
    $this->actingAs($me)->get('/admin/users')->assertOk();

    $this->post('/admin/users', [
        'name' => 'Marwa', 'email' => 'marwa@example.com',
        'password' => 'a-long-password', 'password_confirmation' => 'a-long-password',
    ])->assertRedirect('/admin/users');
    $other = User::where('email', 'marwa@example.com')->sole();
    expect(Hash::check('a-long-password', $other->password))->toBeTrue();

    $this->patch("/admin/users/{$other->id}", ['name' => 'Marwa B', 'email' => 'marwa@example.com', 'password' => ''])
        ->assertRedirect('/admin/users');
    expect($other->fresh()->name)->toBe('Marwa B')
        ->and(Hash::check('a-long-password', $other->fresh()->password))->toBeTrue();

    $this->post('/admin/users', [
        'name' => 'Dup', 'email' => 'marwa@example.com',
        'password' => 'a-long-password', 'password_confirmation' => 'a-long-password',
    ])->assertSessionHasErrors('email');

    $this->delete("/admin/users/{$me->id}")->assertSessionHasErrors('users');
    $this->delete("/admin/users/{$other->id}")->assertRedirect('/admin/users');
    expect(User::count())->toBe(1);
});

it('keeps user management behind login', function () {
    User::factory()->create();
    $this->get('/admin/users')->assertRedirect();
    $this->post('/admin/users', [])->assertRedirect();
});
