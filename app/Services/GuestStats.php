<?php

namespace App\Services;

use App\Models\Invitee;

class GuestStats
{
    /** @return array<string, int> */
    public function summary(): array
    {
        $row = Invitee::query()->selectRaw("
            count(*) as invitations,
            sum(case when status = 'yes' then 1 else 0 end) as accepted,
            sum(case when status = 'no' then 1 else 0 end) as declined,
            sum(case when status = 'pending' then 1 else 0 end) as waiting,
            sum(case when status = 'yes' then guests else 0 end) as guests_coming,
            sum(seats) as total_seats
        ")->first();

        $accepted = (int) $row->accepted;
        $declined = (int) $row->declined;
        $answered = $accepted + $declined;
        $guests = (int) $row->guests_coming;
        $seats = (int) $row->total_seats;

        return [
            'invitations' => (int) $row->invitations,
            'accepted' => $accepted,
            'declined' => $declined,
            'waiting' => (int) $row->waiting,
            'answered' => $answered,
            'guests_coming' => $guests,
            'total_seats' => $seats,
            'acceptance_pct' => $answered ? (int) round($accepted / $answered * 100) : 0,
            'seats_pct' => $seats ? (int) min(100, round($guests / $seats * 100)) : 0,
        ];
    }
}
