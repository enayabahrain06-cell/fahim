<?php

use App\Http\Middleware\EnsureNoAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Behind the Cloudflare tunnel: trust the local proxy hop for the real client IP and scheme.
        $middleware->trustProxies(at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias(['no.admin' => EnsureNoAdmin::class]);
        $middleware->redirectGuestsTo(fn () => User::query()->exists() ? route('admin.login') : route('admin.setup'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Every JSON reply has the shape { ok: false, error: "Human readable message" }.
        $json = fn (Request $request) => $request->expectsJson() || $request->is('i/*/rsvp');

        $exceptions->render(function (ValidationException $e, Request $request) use ($json) {
            if ($json($request)) {
                return response()->json(['ok' => false, 'error' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
            }
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($json) {
            if ($json($request)) {
                return response()->json(['ok' => false, 'error' => 'Please sign in again.'], 401);
            }
        });
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($json) {
            if ($json($request)) {
                return response()->json(['ok' => false, 'error' => 'Too many attempts. Please wait a minute and try again.'], 429, $e->getHeaders());
            }
        });
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($json) {
            if ($json($request)) {
                $status = $e->getStatusCode();
                $error = match ($status) {
                    404 => 'Not found. Please refresh the page.',
                    419 => 'Your session expired. Please reload the page.',
                    default => 'Something went wrong. Please try again.',
                };

                return response()->json(['ok' => false, 'error' => $error], $status, $e->getHeaders());
            }
        });
    })->create();
