<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('asset_number')->nullable();
            $table->string('name');
            $table->date('acquired_on');
            $table->decimal('cost', 20, 2);
            $table->decimal('residual_value', 20, 2)->default(0);
            $table->unsignedInteger('useful_life_months')->default(0);
            $table->foreignId('asset_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('settlement_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('accumulated_depreciation_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('depreciation_expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->string('idempotency_key');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'asset_number']);
            $table->unique(['company_id', 'idempotency_key']);
        });

        Schema::create('depreciation_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('period_month')->default(1);
            $table->date('period_on')->nullable();
            $table->decimal('amount', 20, 2)->default(0);
            $table->decimal('accumulated_amount', 20, 2)->default(0);
            $table->decimal('book_value', 20, 2)->default(0);
            $table->decimal('monthly_amount', 20, 2)->default(0);
            $table->decimal('remaining_value', 20, 2)->default(0);
            $table->timestamps();
            $table->unique(['fixed_asset_id', 'period_month']);
        });

        Schema::create('depreciation_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depreciation_schedule_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('period_month');
            $table->date('posted_on')->nullable();
            $table->decimal('amount', 20, 2);
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key');
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key']);
            $table->unique(['fixed_asset_id', 'depreciation_schedule_id'], 'dep_post_asset_schedule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_postings');
        Schema::dropIfExists('depreciation_schedules');
        Schema::dropIfExists('fixed_assets');
    }
};
