<?php

use App\Models\ActivityLog;
use App\Models\User;

beforeEach(function () {
    $this->log = tempnam(sys_get_temp_dir(), 'errlog');
    file_put_contents($this->log, implode("\n", [
        '[2026-11-01 06:56:03] production.ERROR: SQLSTATE[HY000]: database is locked {"exception":"[object] (X)"}',
        '[stacktrace]',
        '#0 /var/www/fahim/vendor/x.php(1): y()',
        '[2026-11-01 11:06:12] production.ERROR: Vite manifest not found at: /var/www/fahim/public/build/manifest.json {"userId":2}',
    ])."\n");
    config(['wedding.error_log_path' => $this->log]);
    $this->actingAs(admin());
});

afterEach(fn () => @unlink($this->log));

it('shows the activity log and the newest error-log entries, newest first, without stack traces', function () {
    ActivityLog::record('Signed in', null, admin());

    $html = $this->get('/admin/users?tab=logs')->assertOk()->assertSee('Activity')->assertSee('Errors')->getContent();

    expect($html)->toContain('Signed in')->toContain('Vite manifest not found')->toContain('database is locked')
        ->not->toContain('#0 /var/www')->not->toContain('"exception"')
        ->and(strpos($html, 'Vite manifest'))->toBeLessThan(strpos($html, 'database is locked'));
});

it('says so when there are no errors', function () {
    file_put_contents($this->log, '');
    $this->get('/admin/users?tab=logs')->assertOk()->assertSee('No errors.');
});

it('clears the error log file', function () {
    $this->delete('/admin/logs/errors')->assertRedirect('/admin/users?tab=logs');

    expect(file_get_contents($this->log))->toBe('');
});

it('clears the activity log but keeps one line saying who cleared it', function () {
    ActivityLog::record('Signed in', null, admin());
    ActivityLog::record('Signed out', null, admin());

    $this->delete('/admin/logs/activity')->assertRedirect('/admin/users?tab=logs');

    expect(ActivityLog::pluck('action')->all())->toBe(['Cleared the activity log']);
});

it('does not let a view-only user clear logs', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);

    $this->actingAs($viewer)->delete('/admin/logs/errors')->assertForbidden();
    $this->actingAs($viewer)->delete('/admin/logs/activity')->assertForbidden();
    expect(file_get_contents($this->log))->not->toBe('');
});
