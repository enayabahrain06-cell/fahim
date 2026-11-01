<?php

namespace App\Http\Controllers;

use App\Models\Invitee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InviteeController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Invitee::query()->orderByDesc('id')->get()->map->toAdminArray();

        return response()->json(['ok' => true, 'invitees' => $rows]);
    }

    public function destroy(Invitee $invitee): JsonResponse
    {
        $invitee->delete();

        return response()->json(['ok' => true]);
    }

    /** Removes several guests at once (the Select mode in the Guests tab). */
    public function destroyMany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['integer'],
        ]);

        $removed = 0;
        Invitee::query()->whereIn('id', $data['ids'])->get()->each(function (Invitee $invitee) use (&$removed) {
            $invitee->delete();
            $removed++;
        });

        return response()->json(['ok' => true, 'removed' => $removed]);
    }
}
