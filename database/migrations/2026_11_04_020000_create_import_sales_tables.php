<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code');
            $table->string('channel', 80);
            $table->string('original_name');
            $table->foreignId('receipt_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('revenue_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('other_income_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('admin_account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->decimal('total_amount', 20, 2)->default(0);
            $table->string('status')->default('active');
            $table->string('idempotency_key')->unique();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('import_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('order_number');
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('admin_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->decimal('admin_amount', 20, 2)->default(0);
            $table->decimal('invoice_total', 20, 2)->default(0);
            $table->string('status');
            $table->string('skip_reason')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'order_number', 'status']);
        });

        Schema::create('import_order_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_movement_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique('inventory_movement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_order_movements');
        Schema::dropIfExists('import_orders');
        Schema::dropIfExists('import_batches');
    }
};
