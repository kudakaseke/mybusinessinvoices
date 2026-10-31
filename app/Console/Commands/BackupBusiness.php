<?php

namespace App\Console\Commands;

use App\BusinessBackups;
use Illuminate\Console\Command;

class BackupBusiness extends Command
{
    protected $signature = 'business:backup';

    protected $description = 'Back up the database and private expense receipts';

    public function handle(BusinessBackups $backups): int
    {
        try {
            $name = $backups->create();
            $backups->prune();
            $this->info('Private backup created: '.$name);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Backup failed. Check the application log and database backup configuration.');

            return self::FAILURE;
        }
    }
}
