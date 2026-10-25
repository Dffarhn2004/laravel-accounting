<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->date('posted_on');
            $table->string('description');
            $table->string('idempotency_key');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key']);
        });

        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('name');
            $table->string('type')->default('cash');
            $table->string('number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('treasury_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('counter_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('from_cash_account_id')->nullable()->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('to_cash_account_id')->nullable()->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('from_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->date('transacted_on')->nullable();
            $table->date('posted_on')->nullable();
            $table->string('direction')->nullable();
            $table->string('transaction_type')->default('movement');
            $table->string('status')->default('posted');
            $table->decimal('amount', 20, 2);
            $table->string('description');
            $table->string('idempotency_key');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key']);
        });

        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('to_cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->date('transferred_on');
            $table->decimal('amount', 20, 2);
            $table->string('description');
            $table->string('idempotency_key');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transfers');
        Schema::dropIfExists('treasury_transactions');
        Schema::dropIfExists('cash_accounts');
        Schema::dropIfExists('opening_balances');
    }
};
