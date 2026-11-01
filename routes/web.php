<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DetailsController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InviteeController;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PrivateNoticeController;
use App\Http\Controllers\RsvpController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Guest side
Route::get('/', PrivateNoticeController::class)->name('private');
Route::get('/i/{token}', [InvitationController::class, 'show'])
    ->where('token', '[a-f0-9]{8,32}')->middleware('throttle:invite')->name('invite');
Route::post('/i/{token}/rsvp', [RsvpController::class, 'store'])
    ->where('token', '[a-f0-9]{8,32}')->middleware('throttle:rsvp')->name('rsvp');
Route::get('/'.config('wedding.share_slug'), [InvitationController::class, 'join'])->middleware('throttle:invite')->name('join');
Route::post('/'.config('wedding.share_slug'), [RsvpController::class, 'storeJoin'])->middleware('throttle:rsvp-shared')->name('rsvp.join');
Route::post('/i/{token}/join', [RsvpController::class, 'storeShared'])
    ->where('token', '[a-f0-9]{8,32}')->middleware('throttle:rsvp-shared')->name('rsvp.shared');
Route::get('/i/{token}/calendar.ics', [InvitationController::class, 'calendar'])
    ->where('token', '[a-f0-9]{8,32}')->middleware('throttle:invite')->name('invite.ics');

// Admin
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('setup', [SetupController::class, 'show'])->middleware('no.admin')->name('setup');
    Route::post('setup', [SetupController::class, 'store'])->middleware(['no.admin', 'throttle:setup']);
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth', 'log.admin'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('preview', [InvitationController::class, 'preview'])->name('preview');
        Route::post('preview/rsvp', [RsvpController::class, 'storeAsAdmin'])->name('preview.rsvp');
        Route::get('stats', [DashboardController::class, 'stats'])->name('stats');
        Route::get('invitees', [InviteeController::class, 'index'])->name('invitees');
        Route::delete('invitees', [InviteeController::class, 'destroyMany'])->middleware('can.edit');
        Route::delete('invitees/{invitee}', [InviteeController::class, 'destroy'])->middleware('can.edit');
        Route::put('details', [DetailsController::class, 'update'])->middleware('can.edit');
        Route::post('photos/{slot}', [PhotoController::class, 'store'])->middleware(['can.edit', 'throttle:10,1'])->whereIn('slot', ['couple', 'venue']);
        Route::put('photos/{slot}/hidden', [PhotoController::class, 'hide'])->middleware(['can.edit', 'throttle:10,1'])->whereIn('slot', ['couple', 'venue']);
        Route::delete('photos/{slot}', [PhotoController::class, 'destroy'])->middleware(['can.edit', 'throttle:10,1'])->whereIn('slot', ['couple', 'venue']);
        Route::post('music', [MusicController::class, 'store'])->middleware('can.edit');
        Route::put('music/crop', [MusicController::class, 'crop'])->middleware('can.edit');
        Route::delete('music', [MusicController::class, 'destroy'])->middleware('can.edit');
        Route::get('export/pdf', [ExportController::class, 'pdf'])->name('export.pdf');
        Route::get('users', [UserController::class, 'index'])->name('users')->middleware('can.edit');
        Route::post('users', [UserController::class, 'store'])->name('users.store')->middleware('can.edit');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('can.edit');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('can.edit');
        Route::delete('logs/activity', [UserController::class, 'clearActivity'])->name('logs.activity.clear')->middleware('can.edit');
        Route::delete('logs/errors', [UserController::class, 'clearErrors'])->name('logs.errors.clear')->middleware('can.edit');
        Route::get('export/csv', [ExportController::class, 'csv'])->name('export.csv');
    });
});
