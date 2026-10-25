<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->string('unit')->default('unit');
            $table->decimal('minimum_stock', 20, 4)->default(0);
            $table->foreignId('inventory_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('cogs_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('revenue_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'sku']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('movement_type');
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_cost', 20, 2);
            $table->decimal('total_cost', 20, 2);
            $table->decimal('quantity_balance', 20, 4);
            $table->decimal('value_balance', 20, 2);
            $table->date('moved_on');
            $table->string('description')->nullable();
            $table->string('idempotency_key');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(
                ['company_id', 'branch_id', 'inventory_item_id', 'moved_on'],
                'inv_movements_scope_date_idx',
            );
        });

        Schema::create('inventory_cost_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4)->default(0);
            $table->decimal('value', 20, 2)->default(0);
            $table->timestamps();
            $table->unique(
                ['company_id', 'branch_id', 'inventory_item_id'],
                'inv_cost_scope_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_layers');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
    }
};
