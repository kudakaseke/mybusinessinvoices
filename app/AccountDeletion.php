<?php

namespace App;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AccountDeletion
{
    public function delete(User $user): void
    {
        $disk = Storage::disk('local');
        $staging = 'account-deletion-staging/'.$user->id.'/'.Str::uuid();
        $copies = [];
        try {
            foreach (['receipts/'.$user->id, 'business-logos/'.$user->id] as $directory) {
                foreach ($disk->allFiles($directory) as $file) {
                    $copy = $staging.'/'.$file;
                    if (! $disk->copy($file, $copy)) {
                        throw new RuntimeException('Unable to prepare account file recovery.');
                    }
                    $copies[$file] = $copy;
                }
            }
            DB::transaction(fn () => $user->delete());
        } catch (Throwable $exception) {
            foreach ($copies as $file => $copy) {
                if (! $disk->copy($copy, $file)) {
                    throw new RuntimeException('Account file recovery failed; staged copies were retained.', previous: $exception);
                }
            }
            if ($copies && ! $disk->deleteDirectory($staging)) {
                report(new RuntimeException('Unable to remove account deletion recovery copies.'));
            }
            throw $exception;
        }
        if ($copies && ! $disk->deleteDirectory($staging)) {
            report(new RuntimeException('Unable to remove account deletion recovery copies.'));
        }
    }
}
