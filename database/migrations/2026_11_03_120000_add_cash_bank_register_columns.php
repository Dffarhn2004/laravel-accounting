<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('description');
            $table->string('reversal_reason')->nullable()->after('journal_entry_id');
            $table->timestamp('reversed_at')->nullable()->after('reversal_reason');
            $table->foreignId('reversal_journal_entry_id')->nullable()->after('reversed_at')->constrained('journal_entries')->nullOnDelete();
            $table->unique(['company_id', 'reference']);
        });

        Schema::table('expense_payments', function (Blueprint $table) {
            $table->foreignId('treasury_transaction_id')->nullable()->after('expense_id')->constrained('treasury_transactions')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('expense_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treasury_transaction_id');
            $table->dropColumn('reversed_at');
        });

        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'reference']);
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn(['reference', 'reversal_reason', 'reversed_at']);
        });
    }
};
