<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opening_balances', function (Blueprint $table) {
            $table->string('status')->default('posted')->after('journal_entry_id');
            $table->string('reversal_reason')->nullable()->after('status');
            $table->timestamp('reversed_at')->nullable()->after('reversal_reason');
            $table->foreignId('reversal_journal_entry_id')->nullable()->after('reversed_at')->constrained('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opening_balances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn(['status', 'reversal_reason', 'reversed_at']);
        });
    }
};
