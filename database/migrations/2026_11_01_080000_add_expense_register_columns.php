<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Input Beban / Data Beban (PRD 03 #3, 04 #2): description ("Keterangan"), the chosen payment
 * method (Template Jurnal bagian 7), and correction by reversal with a reason (ADR-010).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('description')->nullable()->after('reference');
            $table->string('payment_method', 80)->nullable()->after('description');
            $table->foreignId('reversal_journal_entry_id')->nullable()->after('journal_entry_id')->constrained('journal_entries')->nullOnDelete();
            $table->string('reversal_reason', 500)->nullable()->after('reversal_journal_entry_id');
            $table->timestamp('reversed_at')->nullable()->after('reversal_reason');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn(['description', 'payment_method', 'reversal_reason', 'reversed_at']);
        });
    }
};
