<?php

use App\BusinessBackups;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

function withBackupDatabase(Closure $test): void
{
    Storage::fake('local');
    Storage::fake('backup-fixtures');
    $disk = Storage::disk('backup-fixtures');
    $disk->put('source.sqlite', '');
    $original = DB::getDefaultConnection();
    config([
        'database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $disk->path('source.sqlite'), 'prefix' => '', 'foreign_key_constraints' => true, 'journal_mode' => 'DELETE'],
        'business-backups.path' => $disk->path('backups'), 'cache.default' => 'array',
    ]);
    DB::setDefaultConnection('backup_test');
    Schema::create('backup_probe', function (Blueprint $table): void {
        $table->id();
        $table->string('value');
    });
    DB::table('backup_probe')->insert(['value' => 'original data']);
    try {
        $test(app(BusinessBackups::class));
    } finally {
        DB::purge('backup_test');
        DB::setDefaultConnection($original);
    }
}

test('private backups contain a consistent database and receipt checksums', function () {
    withBackupDatabase(function (BusinessBackups $backups): void {
        Storage::disk('local')->put('receipts/1/receipt.pdf', 'receipt contents');
        $name = $backups->create();
        $path = config('business-backups.path').'/'.$name;
        expect(is_file($path.'/database.sqlite'))->toBeTrue();
        $manifest = json_decode(File::get($path.'/manifest.json'), true);
        expect($manifest['driver'])->toBe('sqlite');
        expect($manifest['files']['receipts/1/receipt.pdf'])->toBe(hash('sha256', 'receipt contents'));
        $copy = new PDO('sqlite:'.$path.'/database.sqlite');
        expect($copy->query('SELECT value FROM backup_probe')->fetchColumn())->toBe('original data');
        unset($copy);
    });
});

test('restore refuses online requests traversal and corrupted backups', function () {
    withBackupDatabase(function (BusinessBackups $backups): void {
        $name = $backups->create();
        expect(fn () => $backups->restore($name))->toThrow(RuntimeException::class, 'maintenance mode');
        $maintenance = Mockery::mock(MaintenanceMode::class);
        $maintenance->shouldReceive('active')->andReturnTrue();
        app()->instance(MaintenanceMode::class, $maintenance);
        expect(fn () => $backups->restore('../outside'))->toThrow(RuntimeException::class, 'Invalid backup name');
        File::append(config('business-backups.path').'/'.$name.'/database.sqlite', 'tampered');
        expect(fn () => $backups->restore($name))->toThrow(RuntimeException::class, 'validation failed');
        expect(DB::table('backup_probe')->value('value'))->toBe('original data');
    });
});

test('restoration recovers database and receipts while creating a safety backup', function () {
    withBackupDatabase(function (BusinessBackups $backups): void {
        Storage::disk('local')->put('receipts/1/receipt.pdf', 'original receipt');
        $name = $backups->create();
        DB::table('backup_probe')->update(['value' => 'changed data']);
        Storage::disk('local')->put('receipts/1/receipt.pdf', 'changed receipt');
        Storage::disk('local')->put('receipts/1/new.pdf', 'later receipt');
        $maintenance = Mockery::mock(MaintenanceMode::class);
        $maintenance->shouldReceive('active')->andReturnTrue();
        app()->instance(MaintenanceMode::class, $maintenance);
        $backups->restore($name);
        expect(DB::table('backup_probe')->value('value'))->toBe('original data');
        expect(Storage::disk('local')->get('receipts/1/receipt.pdf'))->toBe('original receipt');
        Storage::disk('local')->assertMissing('receipts/1/new.pdf');
        expect(File::directories(config('business-backups.path')))->toHaveCount(2);
    });
});

test('backup retention preserves the latest snapshots and unrelated directories', function () {
    withBackupDatabase(function (BusinessBackups $backups): void {
        config(['business-backups.keep' => 2]);
        $first = $backups->create();
        $this->travel(1)->seconds();
        $second = $backups->create();
        $this->travel(1)->seconds();
        $third = $backups->create();
        File::ensureDirectoryExists(config('business-backups.path').'/unrelated');
        $backups->prune();
        expect(is_dir(config('business-backups.path').'/'.$first))->toBeFalse();
        expect(is_dir(config('business-backups.path').'/'.$second))->toBeTrue();
        expect(is_dir(config('business-backups.path').'/'.$third))->toBeTrue();
        expect(is_dir(config('business-backups.path').'/unrelated'))->toBeTrue();
    });
});

test('restore command requires explicit confirmation', function () {
    $this->artisan('business:restore', ['backup' => '20260101-010101-12345678'])->assertFailed();
});

test('the scheduler includes private backups and opted in reminders', function () {
    $commands = collect(app(Schedule::class)->events())->pluck('command')->implode(' ');
    expect($commands)->toContain('business:backup', 'invoices:remind');
});
