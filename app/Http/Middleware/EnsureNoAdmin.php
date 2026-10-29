<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** First-run setup only exists while there is no admin account. */
class EnsureNoAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(User::query()->exists(), 404);

        return $next($request);
    }
}
