<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** View-only users may look at everything but change nothing. */
class EnsureCanEdit
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canEdit(), 403, 'Your account is view-only.');

        return $next($request);
    }
}
