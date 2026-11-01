<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jurnal Umum (PRD 05-LAPORAN.md #1) shows the business source and transaction number of every
 * posting. source_type only holds the posting service class, which cannot tell an invoice from an
 * expense, so each journal now carries an explicit kind and a human reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('source_kind', 30)->nullable()->after('source_id');
            $table->string('reference', 100)->nullable()->after('source_kind');
            $table->index(['company_id', 'source_kind']);
        });

        // Best-effort for rows posted before this migration; the kind is unambiguous for these classes only.
        $map = [
            'App\\Models\\OpeningBalance' => 'opening',
            'App\\Services\\Accounting\\TreasuryPostingService' => 'treasury',
            'App\\Services\\Accounting\\InventoryPostingService' => 'inventory',
            'App\\Services\\Accounting\\FixedAssetService' => 'asset',
            'reversal' => 'reversal',
        ];
        foreach ($map as $type => $kind) {
            DB::table('journal_entries')->where('source_type', $type)->whereNull('source_kind')->update(['source_kind' => $kind]);
        }
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'source_kind']);
            $table->dropColumn(['source_kind', 'reference']);
        });
    }
};
