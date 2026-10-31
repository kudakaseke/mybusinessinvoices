<?php

namespace App\Console\Commands;

use App\BusinessBackups;
use Illuminate\Console\Command;

class RestoreBusiness extends Command
{
    protected $signature = 'business:restore {backup : The backup folder name} {--confirm : Confirm replacement of all business data}';

    protected $description = 'Restore a private backup while the application is in maintenance mode';

    public function handle(BusinessBackups $backups): int
    {
        if (! $this->option('confirm')) {
            $this->error('Restore replaces all business data. Use --confirm only after stopping background workers and putting the app in maintenance mode.');

            return self::FAILURE;
        }
        try {
            $backups->restore($this->argument('backup'));
            $this->info('Backup restored. Run migrations before bringing the application back online.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Restore failed. The application remains in maintenance mode. Check the application log; a safety backup is created before data replacement.');

            return self::FAILURE;
        }
    }
}
