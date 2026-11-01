<?php

namespace App\Http\Controllers;

use App\Models\Invitee;
use App\Services\IcsBuilder;
use App\Services\InvitationDetails;
use App\Services\SharedLink;
use Illuminate\Http\Response;

class InvitationController extends Controller
{
    public function __construct(private InvitationDetails $details, private SharedLink $shared) {}

    public function show(string $token): Response
    {
        $invitee = Invitee::findByToken($token);

        if (! $invitee && $this->shared->matches($token)) {
            return $this->render(null, $token);
        }

        if (! $invitee) {
            return response()->view('invite.private', [], 404);
        }

        // Record the first open, count every open, without touching updated_at.
        Invitee::whereKey($invitee->id)->update([
            'open_count' => $invitee->open_count + 1,
            'opened_at' => $invitee->opened_at ?? now(),
        ]);

        return $this->render($invitee);
    }

    /** The short shared link, /join: one invitation for many guests. */
    public function join(): Response
    {
        return $this->render(null, $this->shared->token());
    }

    public function preview(): Response
    {
        return $this->render(null);
    }

    public function calendar(string $token, IcsBuilder $ics): Response
    {
        if (! Invitee::findByToken($token) && ! $this->shared->matches($token)) {
            return response()->view('invite.private', [], 404);
        }

        return response($ics->build(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="wedding.ics"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function render(?Invitee $invitee, ?string $sharedToken = null): Response
    {
        $d = $this->details->all();

        $config = [
            'preview' => $invitee === null && $sharedToken === null,
            'invitee' => $invitee?->toGuestArray(),
            'rsvpUrl' => $invitee ? route('rsvp', $invitee->token) : ($sharedToken ? route('rsvp.join') : route('admin.preview.rsvp')),
            'csrf' => csrf_token(),
            'startsAt' => $d['starts_at'],
            'rsvpBy' => $d['rsvp_by'],
            'maxGuests' => config('wedding.max_guests'),
            'music' => $this->details->musicEnabled() && $this->details->musicTrack() !== null,
            'musicUrl' => $this->details->musicTrack()['url'] ?? null,
            'musicStart' => $this->details->musicTrack()['start'] ?? 0,
            'musicEnd' => $this->details->musicTrack()['end'] ?? null,
            'initials' => $this->details->initials($d),
            'venue' => $d['venue'],
        ];

        return response()->view('invite.show', [
            'd' => $d,
            'invitee' => $invitee,
            'photos' => $this->details->photos(),
            'hiddenPhotos' => $this->details->hiddenPhotos(),
            'mapEmbed' => $this->details->mapEmbedUrl($d),
            'googleCalendarUrl' => app(IcsBuilder::class)->googleUrl(),
            'config' => $config,
            'calendarUrl' => ($invitee || $sharedToken)
                ? route('invite.ics', $invitee?->token ?? $sharedToken)
                : 'data:text/calendar;charset=utf-8,'.rawurlencode(app(IcsBuilder::class)->build()),
        ])->header('Cache-Control', 'no-store, private');
    }
}
