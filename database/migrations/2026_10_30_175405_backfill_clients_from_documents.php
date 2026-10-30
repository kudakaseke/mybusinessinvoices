<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['invoices', 'quotations'] as $table) {
            DB::table($table)->orderBy('id')->chunkById(100, function ($documents): void {
                foreach ($documents as $document) {
                    if (DB::table('clients')->where('user_id', $document->user_id)
                        ->whereRaw('LOWER(email) = ?', [mb_strtolower($document->customer_email)])->exists()) {
                        continue;
                    }
                    DB::table('clients')->insertOrIgnore([
                        'user_id' => $document->user_id, 'email' => mb_strtolower($document->customer_email),
                        'name' => $document->customer_name, 'phone' => $document->customer_phone, 'address' => $document->customer_address,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
