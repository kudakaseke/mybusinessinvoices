<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'business_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('business_name')->nullable();
                $table->string('google_id')->nullable()->unique();
                $table->string('password')->nullable()->change();
                $table->decimal('usd_to_zwg_rate', 14, 4)->default(26);
                $table->text('business_address')->nullable();
                foreach (['business_email', 'business_phone', 'whatsapp_number', 'website'] as $field) {
                    $table->string($field)->nullable();
                }
                $table->json('default_currencies')->nullable();
                $table->string('base_currency', 3)->default('USD');
                $table->string('target_currency', 3)->default('ZWG');
                $table->decimal('exchange_rate', 14, 6)->default(26);
                $table->decimal('default_tax_rate', 5, 2)->default(0);
                $table->timestamp('business_profile_completed_at')->nullable();
            });
        }
        if (! Schema::hasTable('clients')) {
            Schema::create('clients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('email');
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'email']);
            });
        }
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('currency', 3)->default('USD');
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->timestamps();
                $table->unique(['user_id', 'name']);
            });
        }
        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('merchant');
                $table->string('description');
                $table->string('category');
                $table->date('expense_date');
                $table->string('currency', 3)->default('USD');
                $table->decimal('amount', 14, 2);
                $table->string('receipt_path')->nullable();
                $table->string('receipt_original_name')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('invoice_number');
                $table->date('issue_date');
                $table->date('due_date');
                $table->string('business_name');
                $table->text('business_address')->nullable();
                $table->string('business_email');
                $table->string('business_phone')->nullable();
                $table->string('prepared_by')->nullable();
                $table->string('payment_account_name')->nullable();
                $table->string('payment_account_number')->nullable();
                $table->string('payment_account_currency', 3)->nullable();
                $table->string('payment_bank_name')->nullable();
                $table->string('customer_name');
                $table->text('customer_address')->nullable();
                $table->string('customer_phone')->nullable();
                $table->string('customer_email');
                $table->string('currency', 3)->default('USD');
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->decimal('vat_amount', 14, 2)->default(0);
                $table->decimal('discount_rate', 5, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->string('status')->default('outstanding');
                $table->timestamps();
                $table->unique(['user_id', 'invoice_number']);
            });
        }
        if (! Schema::hasTable('quotations')) {
            Schema::create('quotations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('quotation_number');
                $table->date('issue_date');
                $table->date('due_date');
                $table->string('business_name');
                $table->text('business_address')->nullable();
                $table->string('business_email');
                $table->string('business_phone')->nullable();
                $table->string('prepared_by')->nullable();
                $table->string('payment_account_name')->nullable();
                $table->string('payment_account_number')->nullable();
                $table->string('payment_account_currency', 3)->nullable();
                $table->string('payment_bank_name')->nullable();
                $table->string('customer_name');
                $table->text('customer_address')->nullable();
                $table->string('customer_phone')->nullable();
                $table->string('customer_email');
                $table->string('currency', 3)->default('USD');
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->decimal('vat_amount', 14, 2)->default(0);
                $table->decimal('discount_rate', 5, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'quotation_number']);
            });
        }
        if (! Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->string('description');
                $table->decimal('quantity', 12, 2)->default(1);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('amount', 14, 2)->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('quotation_items')) {
            Schema::create('quotation_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->string('description');
                $table->decimal('quantity', 12, 2)->default(1);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('amount', 14, 2)->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('verification_codes')) {
            Schema::create('verification_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('purpose', 40);
                $table->string('code_hash');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at')->useCurrent()->useCurrentOnUpdate();
                $table->timestamps();
                $table->unique(['user_id', 'purpose']);
                $table->index('expires_at');
            });
        }
    }

    public function down(): void
    {
        // Imported business records must survive a rollback of the reconstruction.
    }
};
