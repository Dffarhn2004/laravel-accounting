<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('activity')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->unique(['company_id', 'branch_id', 'fiscal_year', 'account_id', 'activity', 'revision'], 'budgets_scope_revision_unique');
        });

        Schema::create('budget_monthly_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 20, 2)->default(0);
            $table->timestamps();
            $table->unique(['budget_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_monthly_lines');
        Schema::dropIfExists('budgets');
    }
};
