<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** The invitation details the couple edits in the dashboard, plus the derived date strings. */
class InvitationDetails
{
    public const DEFAULTS = [
        'groom' => 'Fahim',
        'bride' => 'Marwa',
        'groom_arabic' => 'فهيم',
        'bride_arabic' => 'مروة',
        'groom_parents' => 'Hamza and Rahiyanath',
        'bride_parents' => 'Rafeek and Souda',
        'date' => '2026-11-19',
        'time' => '19:30',
        'venue' => 'Adhari New Seasons',
        'venue_address' => 'Hall No. 1',
        'map_link' => 'https://www.google.com/maps/search/?api=1&query=Adhari%20New%20Seasons%20Bahrain',
        'rsvp_by' => '15 November',
        'intro' => 'In a moment filled with joy and gratitude, we invite you to share in our happiness as we begin our new journey together.',
        'join_line' => 'Join us as we celebrate our nikah',
        'note' => '',
        'share_title' => '',
        'share_description' => '',
        'share_message' => '',
        'schedule' => [],
        'music' => true,
    ];

    public function __construct(private Settings $settings) {}

    /** @return array<string, mixed> */
    public function all(): array
    {
        $saved = array_intersect_key((array) $this->settings->get('details', []), self::DEFAULTS);

        return $this->derive(array_merge(self::DEFAULTS, $saved));
    }

    /** @param array<string, mixed> $input validated input */
    public function save(array $input): array
    {
        $details = array_merge(self::DEFAULTS, array_intersect_key((array) $this->settings->get('details', []), self::DEFAULTS));

        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $input)) {
                $details[$key] = $input[$key] ?? (is_array(self::DEFAULTS[$key]) ? [] : '');
            }
        }

        $details['schedule'] = collect((array) $details['schedule'])
            ->map(fn ($row) => ['time' => trim((string) ($row['time'] ?? '')), 'title' => trim((string) ($row['title'] ?? ''))])
            ->filter(fn ($row) => $row['time'] !== '' || $row['title'] !== '')
            ->values()->all();
        $details['music'] = (bool) $details['music'];

        $details = $this->derive($details);
        $this->settings->set('details', $details);
        $this->settings->set('music', ['state' => $details['music'] ? 'on' : 'off', 'theme' => 'nahawand']);

        return $details;
    }

    /** Adds the date strings shown on the invitation, computed in the wedding's timezone. */
    public function derive(array $d): array
    {
        $at = $this->startsAt($d);

        $fill = fn (string $text) => strtr($text, [
            '{couple}' => $d['groom'].' & '.$d['bride'],
            '{date}' => $at->format('j F Y'),
            '{day}' => $at->format('l'),
            '{venue}' => $d['venue'],
        ]);
        $pick = fn (string $key, string $default) => $fill(trim((string) ($d[$key] ?? '')) !== '' ? trim($d[$key]) : $default);

        return array_merge($d, [
            'share_title_text' => $pick('share_title', '{couple} · Wedding Invitation'),
            'share_description_text' => $pick('share_description', ''),
            'share_message_text' => $pick('share_message', "With joy, we invite you to the wedding of {couple}.\nPlease open your personal invitation and confirm your attendance:"),
            'day_name' => $at->format('l'),
            'date_long' => $at->format('j F Y'),
            'short_date' => $at->format('d . m . y'),
            'start_time' => $at->format('g:i A').' onwards',
            'starts_at' => $at->toIso8601String(),
        ]);
    }

    public function startsAt(?array $d = null): CarbonImmutable
    {
        $d ??= $this->all();

        return CarbonImmutable::createFromFormat('Y-m-d H:i', $d['date'].' '.$d['time'], config('wedding.timezone'))
            ->startOfMinute();
    }

    public function couple(?array $d = null): string
    {
        $d ??= $this->all();

        return $d['groom'].' & '.$d['bride'];
    }

    /** "F&M": the first letter of each name, for the wax seals. */
    public function initials(?array $d = null): string
    {
        $d ??= $this->all();

        return Str::upper(mb_substr($d['groom'], 0, 1)).'&'.Str::upper(mb_substr($d['bride'], 0, 1));
    }

    /**
     * Google Maps embed for the venue, shown under the Location heading. Uses the place in the couple's
     * map link (its ?query= / ?q=, or the @lat,lng in the address), else the venue name and address.
     */
    public function mapEmbedUrl(?array $d = null): ?string
    {
        $d ??= $this->all();
        $link = (string) ($d['map_link'] ?? '');
        parse_str((string) parse_url($link, PHP_URL_QUERY), $params);

        $place = trim((string) ($params['query'] ?? $params['q'] ?? ''));
        if ($place === '' && preg_match('#@(-?\d+\.\d+),(-?\d+\.\d+)#', $link, $m)) {
            $place = $m[1].','.$m[2];
        }
        if ($place === '') {
            $place = trim(implode(', ', array_filter([$d['venue'] ?? '', $d['venue_address'] ?? ''])));
        }

        return $place === '' ? null : 'https://www.google.com/maps?q='.rawurlencode($place).'&z=15&output=embed';
    }

    /** @return array{url: string, name: string, uploaded: bool, start: float, end: ?float}|null the music to play (the uploaded track, else public/audio/music.mp3 if it exists) with the part chosen to play */
    public function musicTrack(): ?array
    {
        $file = $this->settings->get('music_file');
        $crop = (array) $this->settings->get('music_crop', []);
        $part = ['start' => (float) ($crop['start'] ?? 0), 'end' => isset($crop['end']) ? (float) $crop['end'] : null];

        if (is_array($file) && ! empty($file['path'])) {
            return ['url' => Storage::disk('public')->url($file['path']).'?v='.($file['v'] ?? 0), 'name' => (string) ($file['name'] ?? 'Your music'), 'uploaded' => true] + $part;
        }

        return file_exists(public_path('audio/music.mp3')) ? ['url' => '/audio/music.mp3', 'name' => 'Built-in music', 'uploaded' => false] + $part : null;
    }

    public function musicEnabled(): bool
    {
        return ($this->settings->get('music')['state'] ?? 'on') === 'on';
    }

    /** Public URL of an uploaded photo with its cache-busting version, or null when the bundled artwork is used. */
    public function photoUrl(string $slot): ?string
    {
        $photo = $this->settings->get("photo_$slot");

        if (! is_array($photo) || empty($photo['path'])) {
            return null;
        }

        return Storage::disk('public')->url($photo['path']).'?v='.($photo['v'] ?? 0);
    }

    /** True when the couple chose to leave this slot's photo (and the built-in artwork) off the invitation. */
    public function photoHidden(string $slot): bool
    {
        return (bool) $this->settings->get("photo_{$slot}_hidden", false);
    }

    /** @return array<string, bool> */
    public function hiddenPhotos(): array
    {
        return collect(config('wedding.photo_slots'))->mapWithKeys(fn ($s) => [$s => $this->photoHidden($s)])->all();
    }

    /** @return array<string, string|null> */
    public function photos(): array
    {
        return collect(config('wedding.photo_slots'))->mapWithKeys(fn ($s) => [$s => $this->photoUrl($s)])->all();
    }
}
