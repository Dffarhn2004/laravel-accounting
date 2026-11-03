<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('depreciation_postings', 'dep_post_schedule_idx')) {
            Schema::table('depreciation_postings', function (Blueprint $table) {
                $table->index('depreciation_schedule_id', 'dep_post_schedule_idx');
            });
        }
        if (! Schema::hasIndex('depreciation_postings', 'dep_post_asset_idx')) {
            Schema::table('depreciation_postings', function (Blueprint $table) {
                $table->index('fixed_asset_id', 'dep_post_asset_idx');
            });
        }

        Schema::table('depreciation_postings', function (Blueprint $table) {
            $table->dropUnique('dep_post_asset_schedule_unique');
            $table->unique(['fixed_asset_id', 'period_month'], 'dep_post_asset_month_unique');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('reversal_reason')->nullable();
        });

        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('name');
            $table->string('source')->default('purchase')->after('payment_method');
            $table->text('description')->nullable()->after('source');
            $table->date('depreciation_from')->nullable()->after('acquired_on');
            $table->foreignId('opening_balance_id')->nullable()->after('journal_entry_id')->constrained('opening_balances')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('status');
            $table->foreignId('reversal_journal_entry_id')->nullable()->after('reversed_at')->constrained('journal_entries')->nullOnDelete();
            $table->string('reversal_reason')->nullable()->after('reversal_journal_entry_id');
        });

        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->unsignedBigInteger('accumulated_depreciation_account_id')->nullable()->change();
            $table->unsignedBigInteger('depreciation_expense_account_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->unsignedBigInteger('accumulated_depreciation_account_id')->nullable(false)->change();
            $table->unsignedBigInteger('depreciation_expense_account_id')->nullable(false)->change();
            $table->dropConstrainedForeignId('opening_balance_id');
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn(['payment_method', 'source', 'description', 'depreciation_from', 'reversed_at', 'reversal_reason']);
        });

        Schema::table('depreciation_postings', function (Blueprint $table) {
            $table->unique(['fixed_asset_id', 'depreciation_schedule_id'], 'dep_post_asset_schedule_unique');
            $table->dropUnique('dep_post_asset_month_unique');
            $table->dropIndex('dep_post_asset_idx');
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn(['reversed_at', 'reversal_reason']);
        });
    }
};
