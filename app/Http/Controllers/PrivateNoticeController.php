<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class PrivateNoticeController extends Controller
{
    /** Anyone without a valid personal link sees the same polite 404 notice. */
    public function __invoke(): Response
    {
        return response()->view('invite.private', [], 404);
    }
}
