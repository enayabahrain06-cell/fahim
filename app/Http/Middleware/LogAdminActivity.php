<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Records every successful change (and export) made in the admin area. */
class LogAdminActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $name = $request->route()?->getName();
        $isExport = in_array($name, ['admin.export.csv', 'admin.export.pdf'], true);
        $isWrite = ! $request->isMethodSafe();

        if (($isWrite || $isExport) && $name !== 'admin.logout' && $response->getStatusCode() < 400) {
            $uri = $request->path();
            $slot = $request->route('slot');
            $action = match (true) {
                $name === 'admin.export.csv' => 'Downloaded guest list (CSV)',
                $name === 'admin.export.pdf' => 'Downloaded guest list (PDF)',
                $name === 'admin.users.store' => 'Added a user',
                $name === 'admin.users.update' => 'Edited a user',
                $name === 'admin.users.destroy' => 'Deleted a user',
                $name === 'admin.logs.activity.clear' => 'Cleared the activity log',
                $name === 'admin.logs.errors.clear' => 'Cleared the error log',
                $name === 'admin.preview.rsvp' => 'Sent a preview reply',
                $request->isMethod('DELETE') && $uri === 'admin/invitees' => 'Removed guests',
                $request->isMethod('DELETE') && str_starts_with($uri, 'admin/invitees/') => 'Removed a guest',
                $uri === 'admin/details' => 'Updated invitation details',
                str_ends_with($uri, '/hidden') => 'Hid or showed a photo',
                $request->isMethod('DELETE') && str_starts_with($uri, 'admin/photos/') => 'Removed a photo',
                str_starts_with($uri, 'admin/photos/') => 'Uploaded a photo',
                $uri === 'admin/music/crop' => 'Trimmed the music',
                $request->isMethod('DELETE') && $uri === 'admin/music' => 'Removed the music',
                $uri === 'admin/music' => 'Uploaded music',
                default => $request->method().' '.$uri,
            };
            $detail = $request->attributes->get('log_detail') ?? ($slot ? "Slot: {$slot}" : null);
            ActivityLog::record($action, $detail, $request->user());
        }

        return $response;
    }
}
