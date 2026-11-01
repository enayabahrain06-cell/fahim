<?php

namespace App\Providers;

use App\Services\InvitationDetails;
use App\Services\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(InvitationDetails::class);
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        RateLimiter::for('invite', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
        // Per link, and per visitor so nobody can guess links by spreading replies over random tokens.
        RateLimiter::for('rsvp', fn (Request $r) => [
            Limit::perMinute(10)->by('rsvp|'.$r->route('token')),
            Limit::perMinute(20)->by('rsvp-ip|'.$r->ip()),
        ]);
        RateLimiter::for('rsvp-shared', fn (Request $r) => [
            Limit::perMinute(120)->by('join|'.$r->route('token')),
            Limit::perMinute(10)->by('join-ip|'.$r->ip()),
        ]);
        RateLimiter::for('setup', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(Str::lower((string) $r->input('email')).'|'.$r->ip()));
    }
}
