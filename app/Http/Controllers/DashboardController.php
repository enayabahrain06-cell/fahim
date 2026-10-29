<?php

namespace App\Http\Controllers;

use App\Services\GuestStats;
use App\Services\InvitationDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DashboardController extends Controller
{
    public function __invoke(InvitationDetails $details): Response
    {
        $d = $details->all();
        $photos = $details->photos();

        return response()->view('admin.dashboard', [
            'd' => $d,
            'photos' => $photos,
            'couple' => $details->couple($d),
            'config' => [
                'details' => $d,
                'photos' => $photos,
                'timezone' => config('wedding.timezone'),
                'maxSeats' => config('wedding.max_seats'),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function stats(GuestStats $stats): JsonResponse
    {
        return response()->json(['ok' => true, 'stats' => $stats->summary(), 'time' => now()->toIso8601String()]);
    }
}
