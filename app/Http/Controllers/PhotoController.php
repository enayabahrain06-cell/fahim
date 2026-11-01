<?php

namespace App\Http\Controllers;

use App\Http\Requests\PhotoRequest;
use App\Services\InvitationDetails;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $this->settings->forget("photo_{$slot}_hidden");

        return $this->state();
    }

    public function destroy(string $slot): JsonResponse
    {
        $this->deleteFile($slot);
        $this->settings->forget("photo_$slot");
        $this->settings->forget("photo_{$slot}_hidden");

        return $this->state();
    }

    /** Hides a slot's picture (uploaded or built-in) from the invitation, or shows it again. */
    public function hide(Request $request, string $slot): JsonResponse
    {
        $request->boolean('hidden')
            ? $this->settings->set("photo_{$slot}_hidden", true)
            : $this->settings->forget("photo_{$slot}_hidden");

        return $this->state();
    }

    private function state(): JsonResponse
    {
        return response()->json(['ok' => true, 'photos' => $this->details->photos(), 'hidden' => $this->details->hiddenPhotos()]);
    }

    private function deleteFile(string $slot): void
    {
        $old = $this->settings->get("photo_$slot")['path'] ?? null;
        if ($old) {
            Storage::disk('public')->delete($old);
        }
    }
}
