<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backing tables for the "Template Jurnal" menu (PRD 02-SYSTEM.md, 10 sections).
 * Sections 1, 2 and 4 reuse branches, fiscal_periods and inventory_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('city')->nullable()->after('name');
            $table->string('brand')->nullable()->after('city');
        });

        // Section 3: products and prices (invoice lines read from here in a later step).
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->string('unit')->default('unit');
            $table->decimal('price', 20, 2)->default(0);
            $table->foreignId('revenue_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'sku']);
        });

        // Section 5: account settings and useful life per asset class.
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('accumulated_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            // 0 = not depreciated (e.g. land).
            $table->unsignedSmallInteger('useful_life_years')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'asset_account_id']);
        });

        // Sections 6-10: editable choice lists that feed other forms' dropdowns.
        Schema::create('journal_template_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('group', 40);
            $table->string('code', 80);
            $table->string('label');
            $table->foreignId('account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->json('meta')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'group', 'code']);
            $table->index(['company_id', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_template_options');
        Schema::dropIfExists('asset_categories');
        Schema::dropIfExists('products');
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['city', 'brand']);
        });
    }
};
