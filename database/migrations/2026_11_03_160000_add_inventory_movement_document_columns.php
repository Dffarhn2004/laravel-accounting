<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('inventory_item_id');
            $table->decimal('freight', 20, 2)->default(0)->after('unit_cost');
            $table->decimal('tax', 20, 2)->default(0)->after('freight');
            $table->string('payment_method')->nullable()->after('movement_type');
            $table->timestamp('reversed_at')->nullable()->after('journal_entry_id');
            $table->foreignId('reversal_journal_entry_id')->nullable()->after('reversed_at')->constrained('journal_entries')->nullOnDelete();
            $table->string('reversal_reason')->nullable()->after('reversal_journal_entry_id');
            $table->unique(['company_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'reference']);
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn(['reference', 'freight', 'tax', 'payment_method', 'reversed_at', 'reversal_reason']);
        });
    }
};
