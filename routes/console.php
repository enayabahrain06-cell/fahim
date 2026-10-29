<?php

use Illuminate\Support\Facades\Schedule;

// On a VPS, run the scheduler from cron: * * * * * php /path/to/artisan schedule:run
Schedule::command('wedding:backup')->dailyAt('03:00')->timezone(config('wedding.timezone'));
