<?php

namespace App\Http\Controllers;

use App\Http\Requests\DetailsRequest;
use App\Services\InvitationDetails;
use Illuminate\Http\JsonResponse;

class DetailsController extends Controller
{
    public function update(DetailsRequest $request, InvitationDetails $details): JsonResponse
    {
        return response()->json(['ok' => true, 'details' => $details->save($request->validated())]);
    }
}
