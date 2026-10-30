<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_on')->nullable();
            $table->string('reference')->nullable();
            $table->uuid('submission_key')->nullable()->unique();
            $table->timestamps();
        });
        DB::table('invoices')->where('status', 'paid')->where('total', '>', 0)->orderBy('id')->chunkById(100, function ($invoices): void {
            foreach ($invoices as $invoice) {
                DB::table('invoice_payments')->insert([
                    'invoice_id' => $invoice->id, 'amount' => $invoice->total, 'paid_on' => null,
                    'reference' => 'Previously marked paid; original payment date unknown',
                    'created_at' => $invoice->updated_at, 'updated_at' => $invoice->updated_at,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
