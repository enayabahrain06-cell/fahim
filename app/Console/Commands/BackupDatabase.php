<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'wedding:backup';

    protected $description = 'Copy the SQLite database to storage/app/backups, keeping the newest backups';

    public function handle(): int
    {
        $source = config('database.connections.sqlite.database');
        if (config('database.default') !== 'sqlite' || ! is_file($source)) {
            $this->error('No SQLite database file was found to back up.');

            return self::FAILURE;
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $target = $dir.'/wedding-'.now()->timezone(config('wedding.timezone'))->format('Y-m-d-His').'.sqlite';

        try {
            // A consistent snapshot even while the site is in use.
            DB::connection('sqlite')->statement('VACUUM INTO ?', [$target]);
        } catch (Throwable) {
            File::copy($source, $target);
        }

        $keep = (int) config('wedding.backups_kept');
        collect(File::glob($dir.'/wedding-*.sqlite'))->sort()->reverse()->slice($keep)->each(fn ($f) => File::delete($f));

        $this->info('Backup saved to '.$target);

        return self::SUCCESS;
    }
}
