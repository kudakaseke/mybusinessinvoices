<?php

namespace App;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class BusinessBackups
{
    public function create(): string
    {
        return Cache::lock('business-backups', 600)->block(10, fn (): string => $this->snapshot());
    }

    private function snapshot(): string
    {
        $root = config('business-backups.path');
        File::ensureDirectoryExists($root, 0700);
        $name = now()->format('Ymd-His').'-'.substr(str_replace('-', '', (string) Str::uuid()), 0, 8);
        $path = $root.DIRECTORY_SEPARATOR.$name;
        File::ensureDirectoryExists($path, 0700);
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Backups currently support MySQL and SQLite connections only.');
        }
        $database = $driver === 'mysql' ? 'database.sql' : 'database.sqlite';
        if ($driver === 'mysql') {
            $this->mysqlProcess(true, $path.'/'.$database)->mustRun();
        } else {
            DB::connection()->unprepared('VACUUM INTO '.DB::connection()->getPdo()->quote($path.'/'.$database));
        }
        $checksums = [$database => hash_file('sha256', $path.'/'.$database)];
        foreach (Storage::disk('local')->allFiles('receipts') as $file) {
            if (! preg_match('#^receipts/[A-Za-z0-9_./-]+$#D', $file) || str_contains($file, '..') || is_link(Storage::disk('local')->path($file))) {
                throw new RuntimeException('An unsafe receipt path was found.');
            }
            File::ensureDirectoryExists(dirname($path.'/'.$file), 0700);
            if (! File::copy(Storage::disk('local')->path($file), $path.'/'.$file)) {
                throw new RuntimeException('Unable to back up a receipt.');
            }
            $checksums[$file] = hash_file('sha256', $path.'/'.$file);
        }
        File::put($path.'/manifest.json', json_encode(['driver' => $driver, 'database' => $database, 'created_at' => now()->toIso8601String(), 'files' => $checksums], JSON_THROW_ON_ERROR));
        foreach (File::allFiles($path) as $file) {
            chmod($file->getPathname(), 0600);
        }

        return $name;
    }

    public function restore(string $name): void
    {
        if (! app()->isDownForMaintenance()) {
            throw new RuntimeException('Put the application in maintenance mode before restoring.');
        }
        Cache::lock('business-backups', 600)->block(10, function () use ($name): void {
            $path = $this->path($name);
            $manifest = json_decode(File::get($path.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $driver = DB::connection()->getDriverName();
            $database = $driver === 'mysql' ? 'database.sql' : 'database.sqlite';
            if (($manifest['driver'] ?? null) !== $driver || ($manifest['database'] ?? null) !== $database || ! isset($manifest['files'][$database])) {
                throw new RuntimeException('This backup does not match the active database driver.');
            }
            foreach ($manifest['files'] as $file => $hash) {
                $resolved = realpath($path.'/'.$file);
                if (($file !== $database && ! preg_match('#^receipts/[A-Za-z0-9_./-]+$#D', $file)) || str_contains($file, '..') || $resolved === false || ! str_starts_with($resolved, $path.DIRECTORY_SEPARATOR) || ! is_file($resolved) || is_link($path.'/'.$file) || ! hash_equals($hash, hash_file('sha256', $resolved))) {
                    throw new RuntimeException('Backup validation failed. Nothing was restored.');
                }
            }
            $this->snapshot();
            if ($driver === 'mysql') {
                $input = fopen($path.'/'.$database, 'rb');
                try {
                    $this->mysqlProcess(false)->setInput($input)->mustRun();
                } finally {
                    fclose($input);
                }
            } else {
                $target = DB::connection()->getConfig('database');
                if ($target === ':memory:') {
                    throw new RuntimeException('An in-memory database cannot be restored.');
                }
                DB::disconnect();
                foreach (['-wal', '-shm'] as $suffix) {
                    if (is_file($target.$suffix)) {
                        throw new RuntimeException('Close SQLite writers and checkpoint the database before restoring.');
                    }
                }
                if (! File::copy($path.'/'.$database, $target)) {
                    throw new RuntimeException('Unable to restore the SQLite database.');
                }
                DB::reconnect();
            }
            foreach (Storage::disk('local')->allFiles('receipts') as $file) {
                if (! Storage::disk('local')->delete($file)) {
                    throw new RuntimeException('Unable to remove a receipt during restore.');
                }
            }
            foreach ($manifest['files'] as $file => $hash) {
                if ($file !== $database && ! Storage::disk('local')->put($file, File::get($path.'/'.$file))) {
                    throw new RuntimeException('Unable to restore a receipt.');
                }
            }
            foreach (['sessions', 'password_reset_tokens', 'cache', 'cache_locks'] as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
        });
    }

    public function prune(): void
    {
        $root = config('business-backups.path');
        if (! is_dir($root)) {
            return;
        }
        $directories = collect(File::directories($root))->filter(fn (string $path): bool => preg_match('/^\\d{8}-\\d{6}-[a-f0-9]{8}$/D', basename($path)) && is_file($path.'/manifest.json'))->sortDesc()->values();
        foreach ($directories->slice(max(2, (int) config('business-backups.keep', 14))) as $directory) {
            File::deleteDirectory($this->path(basename($directory)));
        }
    }

    private function path(string $name): string
    {
        if (! preg_match('/^\\d{8}-\\d{6}-[a-f0-9]{8}$/D', $name)) {
            throw new RuntimeException('Invalid backup name.');
        }
        $root = realpath(config('business-backups.path'));
        $path = $root === false ? false : realpath($root.DIRECTORY_SEPARATOR.$name);
        if ($root === false || $path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || is_link($root.DIRECTORY_SEPARATOR.$name)) {
            throw new RuntimeException('Backup not found.');
        }

        return $path;
    }

    private function mysqlProcess(bool $dump, ?string $destination = null): Process
    {
        $configuration = DB::connection()->getConfig();
        $arguments = [config('business-backups.'.($dump ? 'dump_binary' : 'restore_binary')),
            '--host='.$configuration['host'], '--port='.($configuration['port'] ?? 3306), '--user='.$configuration['username']];
        if ($dump) {
            $arguments = [...$arguments, '--single-transaction', '--skip-lock-tables', '--hex-blob', '--routines', '--triggers', '--result-file='.$destination];
        }
        if (! preg_match('/^[A-Za-z0-9_]+$/D', $configuration['database'])) {
            throw new RuntimeException('The configured database name is not supported by the backup tool.');
        }
        $arguments[] = $configuration['database'];

        return (new Process($arguments, base_path(), ['MYSQL_PWD' => $configuration['password'] ?? '']))->setTimeout(300);
    }
}
