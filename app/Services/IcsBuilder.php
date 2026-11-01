<?php

namespace App\Services;

use Carbon\CarbonInterface;

/** A single-event iCalendar file for the wedding (RFC 5545). */
class IcsBuilder
{
    public const DURATION_HOURS = 4;

    public function __construct(private InvitationDetails $details) {}

    public function build(): string
    {
        $d = $this->details->all();
        $start = $this->details->startsAt($d)->utc();
        $end = $start->addHours(self::DURATION_HOURS);
        $couple = $this->details->couple($d);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Wedding Invitation//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$this->stamp($start).'-'.substr(sha1($couple), 0, 10).'@wedding-invitation',
            'DTSTAMP:'.$this->stamp(now()),
            'DTSTART:'.$this->stamp($start),
            'DTEND:'.$this->stamp($end),
            'SUMMARY:'.$this->escape($couple.' Wedding'),
            'LOCATION:'.$this->escape(implode(', ', array_filter([$d['venue'], $d['venue_address']]))),
        ];

        if (! empty($d['map_link'])) {
            $lines[] = 'URL:'.$d['map_link'];
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /** The same event as a Google Calendar "add event" link, for the Save Date button. */
    public function googleUrl(): string
    {
        $d = $this->details->all();
        $start = $this->details->startsAt($d)->utc();

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $this->details->couple($d).' Wedding',
            'dates' => $this->stamp($start).'/'.$this->stamp($start->addHours(self::DURATION_HOURS)),
            'location' => implode(', ', array_filter([$d['venue'], $d['venue_address']])),
            'details' => $d['map_link'] ?: '',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function stamp(CarbonInterface $time): string
    {
        return $time->utc()->format('Ymd\THis\Z');
    }

    private function escape(string $text): string
    {
        return strtr($text, ['\\' => '\\\\', ';' => '\;', ',' => '\,', "\r\n" => '\n', "\n" => '\n']);
    }

    /** Lines longer than 75 octets are folded with CRLF and a space, never inside a UTF-8 character. */
    private function fold(string $line): string
    {
        $out = '';
        while (strlen($line) > 75) {
            $cut = 75;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
        }

        return $out.$line;
    }
}
