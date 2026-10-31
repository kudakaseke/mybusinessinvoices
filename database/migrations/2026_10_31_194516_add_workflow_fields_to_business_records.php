<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table): void {
            $table->softDeletes();
            $table->string('void_reason')->nullable();
            $table->date('voided_on')->nullable();
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('quotation_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('last_reminded_at')->nullable();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('payment_reminders_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('payment_reminders_enabled'));
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('quotation_id');
            $table->dropColumn('last_reminded_at');
        });
        Schema::table('invoice_payments', fn (Blueprint $table) => $table->dropColumn(['deleted_at', 'void_reason', 'voided_on']));
    }
};
