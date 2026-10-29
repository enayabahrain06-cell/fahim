<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'wedding:admin
        {--name= : Name shown in the dashboard}
        {--email= : Email used to sign in}
        {--password= : New password (asked for when left out)}';

    protected $description = 'Create the single admin account, or reset its email and password';

    public function handle(): int
    {
        $user = User::query()->first();

        $name = $this->option('name') ?? ($user?->name ?? $this->ask('Name', 'Fahim & Marwa'));
        $email = $this->option('email') ?? $this->ask('Email', $user?->email);
        $password = $this->option('password') ?? $this->secret('New password (at least 10 characters)');
        $confirm = $this->option('password') ?? $this->secret('Repeat the password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirm],
            ['name' => 'required|max:120', 'email' => 'required|email|max:190', 'password' => 'required|min:10|confirmed'],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use (&$user, $name, $email, $password) {
            $user ??= new User;
            $user->forceFill(['name' => $name, 'email' => $email, 'password' => $password])->save();
            // Only one admin is allowed.
            User::query()->whereKeyNot($user->id)->delete();
            // Sign out every open session so the old password stops working everywhere.
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->delete();
            }
        });

        $this->info($user->wasRecentlyCreated ? "Admin account created for $email." : "Admin account updated for $email.");

        return self::SUCCESS;
    }
}
