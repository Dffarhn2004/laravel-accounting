<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->decimal('rate', 8, 4);
            $table->string('version');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code', 'version']);
        });

        Schema::create('tax_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_code_id')->constrained()->restrictOnDelete();
            $table->string('source_type');
            $table->string('source_id');
            $table->date('tax_date');
            $table->decimal('tax_base', 20, 2);
            $table->decimal('tax_amount', 20, 2);
            $table->string('idempotency_key');
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'tax_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_transactions');
        Schema::dropIfExists('tax_codes');
    }
};
