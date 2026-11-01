<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Edit/Hapus" of a posted invoice is a reversal with a reason (ADR-010): the original journal is
 * never deleted, a mirror journal is posted and the invoice is marked reversed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('reversal_journal_entry_id')->nullable()->after('journal_entry_id')->constrained('journal_entries')->nullOnDelete();
            $table->string('reversal_reason', 500)->nullable()->after('reversal_journal_entry_id');
            $table->timestamp('reversed_at')->nullable()->after('reversal_reason');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn(['reversal_reason', 'reversed_at']);
        });
    }
};
