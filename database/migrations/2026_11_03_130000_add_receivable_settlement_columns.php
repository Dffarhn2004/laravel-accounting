<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('description')->nullable()->after('amount');
            $table->string('payment_method')->nullable()->after('description');
            $table->decimal('pph23_amount', 20, 2)->default(0)->after('payment_method');
            $table->string('status')->default('posted')->after('pph23_amount');
            $table->string('reversal_reason')->nullable()->after('journal_entry_id');
            $table->timestamp('reversed_at')->nullable()->after('reversal_reason');
            $table->foreignId('reversal_journal_entry_id')->nullable()->after('reversed_at')->constrained('journal_entries')->nullOnDelete();
            $table->unique(['company_id', 'receipt_number']);
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn(['description', 'payment_method', 'pph23_amount', 'status', 'reversal_reason', 'reversed_at']);
        });
    }
};
