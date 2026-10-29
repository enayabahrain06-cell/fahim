<?php

namespace Database\Seeders;

use App\Models\Invitee;
use App\Services\InvitationDetails;
use Illuminate\Database\Seeder;

/** The wedding details and six sample invitations: `php artisan db:seed --class=DemoSeeder`. */
class DemoSeeder extends Seeder
{
    public function run(InvitationDetails $details): void
    {
        $details->save(InvitationDetails::DEFAULTS);

        $guests = [
            ['Ahmed Ali & family', '97333334444', 4, 'yes', ['Sara Ahmed', 'Yusuf Ahmed'], '-2 days'],
            ['Fatima Rashid', null, 2, 'yes', ['Huda Rashid'], '-2 days'],
            ['Mohammed Saleem', null, 1, 'no', [], '-1 day'],
            ['Rahul Nair', null, 4, 'yes', ['Anjali Nair', 'Dev Nair', 'Meera Nair'], '-1 day'],
            ['عائشة كريم', null, 1, 'pending', [], null],
            ['Shameer K', null, 5, 'pending', [], null],
        ];

        foreach ($guests as [$name, $phone, $seats, $status, $extra, $when]) {
            $invitee = Invitee::create(['name' => $name, 'phone' => $phone, 'seats' => $seats]);
            if ($status !== 'pending') {
                $names = $status === 'yes' ? [$name, ...$extra] : null;
                $invitee->forceFill([
                    'status' => $status,
                    'guests' => $names ? count($names) : 0,
                    'guest_names' => $names,
                    'replied_at' => now()->modify($when),
                    'opened_at' => now()->modify($when)->subHour(),
                    'open_count' => 2,
                ])->save();
                $invitee->logs()->create(['status' => $status, 'guests' => $invitee->guests, 'guest_names' => $names]);
            }
        }
    }
}
