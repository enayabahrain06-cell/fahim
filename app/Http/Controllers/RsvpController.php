<?php

namespace App\Http\Controllers;

use App\Http\Requests\RsvpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RsvpController extends Controller
{
    public function store(RsvpRequest $request): JsonResponse
    {
        $invitee = $request->invitee();
        $attending = $request->attending();
        $guests = $request->guestCount();
        $names = $request->replyNames();

        DB::transaction(function () use ($invitee, $attending, $guests, $names, $request) {
            $invitee->forceFill([
                'status' => $attending ? 'yes' : 'no',
                'guests' => $guests,
                'guest_names' => $names,
                'replied_at' => now(),
            ])->save();

            $invitee->logs()->create([
                'status' => $invitee->status,
                'guests' => $invitee->guests,
                'guest_names' => $names,
                'ip_hash' => hash('sha256', $request->ip().config('app.key')),
                'user_agent' => Str::limit((string) $request->userAgent(), 252),
            ]);
        });

        return response()->json(['ok' => true]);
    }
}
