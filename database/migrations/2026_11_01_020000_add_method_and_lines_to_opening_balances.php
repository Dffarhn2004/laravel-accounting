<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opening_balances', function (Blueprint $table) {
            // 'saldo_neraca' | 'modal_awal'. NULL = posted by the older generic journal page.
            // The two methods are mutually exclusive per branch + fiscal period.
            $table->string('method', 30)->nullable()->after('fiscal_period_id');
        });

        // Audit trail for what the user entered (journal lines hold only the result).
        Schema::create('opening_balance_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->decimal('amount', 20, 2);
            $table->string('side', 6); // debit | credit (resulting journal side)
            // Fixed-asset cut-off info kept for the asset register (acquired_on, useful_life_years, accumulated).
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_lines');
        Schema::table('opening_balances', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }
};
