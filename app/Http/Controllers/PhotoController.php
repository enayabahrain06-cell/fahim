<?php

namespace App\Http\Controllers;

use App\Http\Requests\PhotoRequest;
use App\Services\InvitationDetails;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class PhotoController extends Controller
{
    public const MAX_SIDE = 1600;

    public function __construct(private Settings $settings, private InvitationDetails $details) {}

    public function store(PhotoRequest $request, string $slot): JsonResponse
    {
        try {
            // Decoding and re-encoding drops EXIF and anything hidden in the file.
            $image = (new ImageManager(new Driver))->read($request->file('photo')->getRealPath())
                ->scaleDown(self::MAX_SIDE, self::MAX_SIDE);
            $jpeg = (string) $image->toJpeg(85);
        } catch (Throwable) {
            return response()->json(['ok' => false, 'error' => 'That file could not be read as a photo. Please choose a JPG, PNG or WebP image.'], 422);
        }

        $this->deleteFile($slot);
        $path = "invitation/$slot.jpg";
        Storage::disk('public')->put($path, $jpeg);
        $this->settings->set("photo_$slot", ['path' => $path, 'v' => now()->timestamp]);

        return response()->json(['ok' => true, 'photos' => $this->details->photos()]);
    }

    public function destroy(string $slot): JsonResponse
    {
        $this->deleteFile($slot);
        $this->settings->forget("photo_$slot");

        return response()->json(['ok' => true, 'photos' => $this->details->photos()]);
    }

    private function deleteFile(string $slot): void
    {
        $old = $this->settings->get("photo_$slot")['path'] ?? null;
        if ($old) {
            Storage::disk('public')->delete($old);
        }
    }
}
