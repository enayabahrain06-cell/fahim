<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DetailsController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InviteeController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PrivateNoticeController;
use App\Http\Controllers\RsvpController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

// Guest side
Route::get('/', PrivateNoticeController::class)->name('private');
Route::get('/i/{token}', [InvitationController::class, 'show'])
    ->where('token', '[a-f0-9]{8,32}')->middleware('throttle:invite')->name('invite');
Route::post('/i/{token}/rsvp', [RsvpController::class, 'store'])
    ->where('token', '[a-f0-9]{8,32}')->middleware('throttle:rsvp')->name('rsvp');
Route::get('/i/{token}/calendar.ics', [InvitationController::class, 'calendar'])
    ->where('token', '[a-f0-9]{8,32}')->middleware('throttle:invite')->name('invite.ics');

// Admin
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('setup', [SetupController::class, 'show'])->middleware('no.admin')->name('setup');
    Route::post('setup', [SetupController::class, 'store'])->middleware(['no.admin', 'throttle:setup']);
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('preview', [InvitationController::class, 'preview'])->name('preview');
        Route::get('stats', [DashboardController::class, 'stats'])->name('stats');
        Route::get('invitees', [InviteeController::class, 'index'])->name('invitees');
        Route::post('invitees', [InviteeController::class, 'store']);
        Route::post('invitees/bulk', [InviteeController::class, 'bulk']);
        Route::patch('invitees/{invitee}', [InviteeController::class, 'update']);
        Route::post('invitees/{invitee}/reset', [InviteeController::class, 'reset']);
        Route::post('invitees/{invitee}/regenerate', [InviteeController::class, 'regenerate']);
        Route::delete('invitees/{invitee}', [InviteeController::class, 'destroy']);
        Route::put('details', [DetailsController::class, 'update']);
        Route::post('photos/{slot}', [PhotoController::class, 'store'])->whereIn('slot', ['couple', 'venue']);
        Route::delete('photos/{slot}', [PhotoController::class, 'destroy'])->whereIn('slot', ['couple', 'venue']);
        Route::get('export/pdf', [ExportController::class, 'pdf'])->name('export.pdf');
        Route::get('export/csv', [ExportController::class, 'csv'])->name('export.csv');
    });
});
