<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The old p7h.me address is retired: send visitors to the real domain. */
class RedirectRetiredDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        if (preg_match('/(^|\.)p7h\.me$/i', $request->getHost())) {
            return redirect()->away(rtrim(config('app.url'), '/').$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
