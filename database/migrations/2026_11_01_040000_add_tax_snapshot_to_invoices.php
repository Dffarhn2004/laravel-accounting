<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('dpp', 20, 2)->default(0)->after('issued_on');
            $table->decimal('discount_total', 20, 2)->default(0)->after('dpp');
            $table->decimal('vat_rate', 5, 2)->default(0)->after('discount_total');
            $table->decimal('vat_amount', 20, 2)->default(0)->after('vat_rate');
            $table->decimal('pph23_rate', 5, 2)->default(0)->after('vat_amount');
            $table->decimal('pph23_amount', 20, 2)->default(0)->after('pph23_rate');
            $table->foreignId('vat_output_account_id')->nullable()->after('revenue_account_id')->constrained('accounts')->restrictOnDelete();
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('unit_price');
            $table->decimal('line_total', 20, 2)->default(0)->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', fn (Blueprint $table) => $table->dropColumn(['discount_percent', 'line_total']));
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vat_output_account_id');
            $table->dropColumn(['dpp', 'discount_total', 'vat_rate', 'vat_amount', 'pph23_rate', 'pph23_amount']);
        });
    }
};
