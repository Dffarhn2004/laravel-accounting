<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Input Invoice (PRD 03-INPUTAN-TRANSAKSI.md #1): product groups per line, up to 3 other
 * incomes, payment at issue, discount booked to its own expense account (the reference's
 * "Beban Discount" column) and evidence attachments (max 5, reused by every transaction type).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_method', 80)->nullable()->after('status');
            $table->decimal('paid_amount', 20, 2)->default(0)->after('payment_method');
            $table->foreignId('payment_account_id')->nullable()->after('paid_amount')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('discount_account_id')->nullable()->after('payment_account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('other_income_total', 20, 2)->default(0)->after('discount_total');
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('invoice_id')->constrained('products')->nullOnDelete();
            // "Kelompok Pendapatan" of the line; falls back to the invoice's revenue account.
            $table->foreignId('revenue_account_id')->nullable()->after('product_id')->constrained('accounts')->restrictOnDelete();
        });

        Schema::create('invoice_other_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('amount', 20, 2);
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('invoice_other_incomes');
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revenue_account_id');
            $table->dropConstrainedForeignId('product_id');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_account_id');
            $table->dropConstrainedForeignId('payment_account_id');
            $table->dropColumn(['payment_method', 'paid_amount', 'other_income_total']);
        });
    }
};
