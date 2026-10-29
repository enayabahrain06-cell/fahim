<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Many guests at once: pasted lines or an uploaded CSV, both "name, seats, phone". */
class BulkInviteeRequest extends FormRequest
{
    public const MAX_LINES = 500;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'text' => ['required_without:file', 'nullable', 'string', 'max:60000'],
            'file' => ['required_without:text', 'nullable', 'file', 'mimes:csv,txt', 'max:1024'],
        ];
    }

    public function messages(): array
    {
        return [
            'text.required_without' => 'Write at least one guest, or choose a CSV file.',
            'file.mimes' => 'Please choose a .csv file.',
        ];
    }

    /** @return list<string> */
    public function lines(): array
    {
        $raw = $this->hasFile('file')
            ? (string) file_get_contents($this->file('file')->getRealPath())
            : (string) $this->input('text');

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // Excel's UTF-8 marker

        return preg_split('/\r\n|\r|\n/', $raw);
    }

    /**
     * Turns one line into a guest, or a reason it was skipped.
     *
     * @return array{name: string, seats: int, phone: ?string}|string
     */
    public static function parseLine(string $line): array|string
    {
        $parts = array_map('trim', str_getcsv($line, str_contains($line, "\t") ? "\t" : (str_contains($line, ';') && ! str_contains($line, ',') ? ';' : ','), '"', ''));
        $name = mb_substr(trim((string) array_shift($parts), " \"'"), 0, 200);

        if ($name === '') {
            return 'the name is missing';
        }
        if (mb_strlen($name) > 120) {
            return 'the name is longer than 120 characters';
        }

        $seats = 1;
        $phone = null;
        foreach ($parts as $part) {
            $digits = preg_replace('/\D+/', '', $part);
            if ($digits === '') {
                if ($part !== '') {
                    return "\"$part\" is not a number of seats or a phone number";
                }

                continue;
            }
            if (strlen($digits) <= 2) {
                $seats = (int) $digits;
                if ($seats < 1 || $seats > config('wedding.max_seats')) {
                    return 'seats must be between 1 and '.config('wedding.max_seats');
                }
            } elseif (strlen($digits) < 6 || strlen($digits) > 20) {
                return "\"$part\" does not look like a WhatsApp number";
            } else {
                $phone = $digits;
            }
        }

        return ['name' => $name, 'seats' => $seats, 'phone' => $phone];
    }
}
