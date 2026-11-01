<?php

namespace App\Http\Controllers;

use App\Http\Requests\RsvpRequest;
use App\Models\Invitee;
use Illuminate\Http\JsonResponse;
use App\Services\SharedLink;
use Illuminate\Http\Request;
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

    public function storeJoin(Request $request): JsonResponse
    {
        return $this->saveNew($request);
    }

    /** A reply through the old long shared link: saved as its own invitation, so nobody overwrites anybody. */
    public function storeShared(Request $request, string $token, SharedLink $shared): JsonResponse
    {
        abort_unless($shared->matches($token), 404);

        return $this->saveNew($request);
    }

    /** The couple replying from the preview, e.g. for a guest who answered by phone: saved as its own invitation. */
    public function storeAsAdmin(Request $request): JsonResponse
    {
        return $this->saveNew($request);
    }

    private function saveNew(Request $request): JsonResponse
    {
        $data = $request->validate([
            'attending' => ['required', 'boolean'],
            'count' => ['exclude_if:attending,false', 'required', 'integer', 'min:1', 'max:'.config('wedding.max_guests')],
            'name' => ['exclude_if:attending,false', 'required', 'string', 'max:80'],
        ]);
        $attending = (bool) $data['attending'];
        $name = $attending ? trim($data['name']) : 'Guest (declined)';
        $guests = $attending ? (int) $data['count'] : 0;

        $invitee = Invitee::create(['name' => $name, 'seats' => max(1, $guests)]);
        $invitee->forceFill([
            'status' => $attending ? 'yes' : 'no',
            'guests' => $guests,
            'guest_names' => $attending ? [$name] : null,
            'replied_at' => now(),
        ])->save();
        $invitee->logs()->create([
            'status' => $invitee->status,
            'guests' => $guests,
            'guest_names' => $attending ? [$name] : null,
            'ip_hash' => hash('sha256', $request->ip().config('app.key')),
            'user_agent' => Str::limit((string) $request->userAgent(), 252),
        ]);

        return response()->json(['ok' => true]);
    }
}
