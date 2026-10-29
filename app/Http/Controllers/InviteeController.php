<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkInviteeRequest;
use App\Http\Requests\InviteeRequest;
use App\Models\Invitee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InviteeController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Invitee::query()->orderByDesc('id')->get()->map->toAdminArray();

        return response()->json(['ok' => true, 'invitees' => $rows]);
    }

    public function store(InviteeRequest $request): JsonResponse
    {
        $invitee = Invitee::create([
            'name' => $request->validated('name'),
            'seats' => $request->validated('seats') ?? 1,
            'phone' => $request->validated('phone'),
            'notes' => $request->validated('notes'),
        ]);

        return response()->json(['ok' => true, 'invitee' => $invitee->toAdminArray()], 201);
    }

    public function bulk(BulkInviteeRequest $request): JsonResponse
    {
        $added = [];
        $skipped = [];

        DB::transaction(function () use ($request, &$added, &$skipped) {
            foreach ($request->lines() as $i => $line) {
                $line = trim($line);
                if ($line === '' || ($i === 0 && preg_match('/^"?name"?\s*([,;\t]|$)/i', $line))) {
                    continue; // blank line or a CSV header row
                }
                if (count($added) + count($skipped) >= BulkInviteeRequest::MAX_LINES) {
                    $skipped[] = ['line' => $i + 1, 'text' => $line, 'reason' => 'only '.BulkInviteeRequest::MAX_LINES.' guests can be added at once'];

                    break;
                }

                $guest = BulkInviteeRequest::parseLine($line);
                if (is_string($guest)) {
                    $skipped[] = ['line' => $i + 1, 'text' => $line, 'reason' => $guest];

                    continue;
                }

                $added[] = Invitee::create($guest)->toAdminArray();
            }
        });

        return response()->json([
            'ok' => true,
            'added' => count($added),
            'invitees' => $added,
            'skipped' => $skipped,
        ], $added ? 201 : 200);
    }

    public function update(InviteeRequest $request, Invitee $invitee): JsonResponse
    {
        // Seats don't limit replies, so a confirmed number of guests is kept as the guest gave it.
        $invitee->fill($request->validated())->save();

        return response()->json(['ok' => true, 'invitee' => $invitee->toAdminArray()]);
    }

    public function reset(Invitee $invitee): JsonResponse
    {
        $invitee->clearReply();

        return response()->json(['ok' => true, 'invitee' => $invitee->toAdminArray()]);
    }

    public function regenerate(Invitee $invitee): JsonResponse
    {
        $invitee->regenerateToken();

        return response()->json(['ok' => true, 'invitee' => $invitee->toAdminArray()]);
    }

    public function destroy(Invitee $invitee): JsonResponse
    {
        $invitee->delete();

        return response()->json(['ok' => true]);
    }
}
