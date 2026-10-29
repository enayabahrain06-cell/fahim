<?php

namespace App\Http\Controllers;

use App\Models\Invitee;
use App\Services\GuestStats;
use App\Services\InvitationDetails;
use App\Services\PdfText;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(private InvitationDetails $details) {}

    public function pdf(GuestStats $stats, PdfText $text): Response
    {
        File::ensureDirectoryExists(storage_path('fonts')); // dompdf's font cache

        $d = $this->details->all();
        $invitees = Invitee::query()->get()->sortBy(fn ($i) => Str::lower($i->name), SORT_NATURAL)->values();

        $pdf = Pdf::loadView('exports.guest-list', [
            'd' => $d,
            'couple' => $this->details->couple($d),
            'stats' => $stats->summary(),
            'coming' => $invitees->where('status', 'yes')->values(),
            'declined' => $invitees->where('status', 'no')->values(),
            'waiting' => $invitees->where('status', 'pending')->values(),
            'generated' => now()->timezone(config('wedding.timezone')),
            'tz' => config('wedding.timezone'),
            't' => $text,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($this->fileName('pdf'));
    }

    public function csv(): StreamedResponse
    {
        $tz = config('wedding.timezone');
        $time = fn ($t) => $t?->timezone($tz)->format('Y-m-d H:i');

        return response()->streamDownload(function () use ($time) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8 (Arabic names)
            fputcsv($out, ['id', 'name', 'phone', 'seats', 'status', 'guests', 'guest_names', 'replied_at', 'opened_at', 'open_count', 'notes', 'token', 'link', 'created_at'], escape: '');

            Invitee::query()->orderBy('name')->each(function (Invitee $i) use ($out, $time) {
                fputcsv($out, array_map([$this, 'safeCell'], [
                    $i->id, $i->name, $i->phone, $i->seats, $i->status, $i->guests,
                    implode(', ', $i->guest_names ?? []), $time($i->replied_at), $time($i->opened_at),
                    $i->open_count, $i->notes, $i->token, $i->link(), $time($i->created_at),
                ]), escape: '');
            });

            fclose($out);
        }, $this->fileName('csv'), ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /** Stops spreadsheet apps from running a guest-entered name as a formula. */
    public function safeCell(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function fileName(string $ext): string
    {
        $d = $this->details->all();
        $names = Str::slug($d['groom']).'-'.Str::slug($d['bride']);
        $names = trim($names, '-') !== '' ? Str::title($names) : 'Wedding';

        return $names.'-guest-list-'.now()->timezone(config('wedding.timezone'))->format('Y-m-d').'.'.$ext;
    }
}
