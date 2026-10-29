<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');
        $response->headers->set('Content-Security-Policy', $this->policy());

        return $response;
    }

    private function policy(): string
    {
        $script = ["'self'"];
        $style = ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'];
        $connect = ["'self'"];

        // While `npm run dev` is running, assets come from the Vite dev server.
        if (Vite::isRunningHot()) {
            $hot = rtrim((string) file_get_contents(public_path('hot')));
            $script[] = $hot;
            $style[] = $hot;
            $connect[] = $hot;
            $connect[] = preg_replace('#^http#', 'ws', $hot);
        }

        // Laravel's local error page needs inline scripts.
        if (config('app.debug')) {
            $script[] = "'unsafe-inline'";
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            'connect-src '.implode(' ', $connect),
            "media-src 'self'",
            'frame-src https://www.google.com', // the venue map on the invitation
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
