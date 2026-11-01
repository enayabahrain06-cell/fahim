<?php

namespace App\Http\Controllers;

use App\Http\Requests\MusicRequest;
use App\Services\InvitationDetails;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MusicController extends Controller
{
    public function __construct(private Settings $settings, private InvitationDetails $details) {}

    public function store(MusicRequest $request): JsonResponse
    {
        $file = $request->file('music');
        $ext = match ($file->getMimeType()) {
            'audio/ogg' => 'ogg',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/webm' => 'webm',
            'audio/mp4', 'audio/x-m4a', 'audio/aac', 'video/mp4' => 'm4a',
            default => 'mp3',
        };

        $this->deleteFile();
        $path = 'invitation/music-'.now()->timestamp.'.'.$ext;
        Storage::disk('public')->putFileAs('invitation', $file, basename($path));
        $name = Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 60, '');
        $this->settings->forget('music_crop');
        $this->settings->set('music_file', ['path' => $path, 'v' => now()->timestamp, 'name' => $name !== '' ? $name : 'Your music']);

        return response()->json(['ok' => true, 'music' => $this->details->musicTrack()]);
    }

    /** Which part of the song plays: from `start` to `end` seconds, then back to `start`. The file itself is untouched. */
    public function crop(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'numeric', 'min:0', 'max:86400'],
            'end' => ['nullable', 'numeric', 'gt:start', 'max:86400'],
        ], ['end.gt' => 'The end must be after the start.']);

        $data['start'] = round((float) $data['start'], 2);
        $data['end'] = isset($data['end']) ? round((float) $data['end'], 2) : null;

        if ($data['start'] == 0 && $data['end'] === null) {
            $this->settings->forget('music_crop');
        } else {
            $this->settings->set('music_crop', $data);
        }

        return response()->json(['ok' => true, 'music' => $this->details->musicTrack()]);
    }

    public function destroy(): JsonResponse
    {
        $this->deleteFile();
        $this->settings->forget('music_file');
        $this->settings->forget('music_crop');

        return response()->json(['ok' => true, 'music' => $this->details->musicTrack()]);
    }

    private function deleteFile(): void
    {
        $old = $this->settings->get('music_file')['path'] ?? null;
        if ($old) {
            Storage::disk('public')->delete($old);
        }
    }
}
