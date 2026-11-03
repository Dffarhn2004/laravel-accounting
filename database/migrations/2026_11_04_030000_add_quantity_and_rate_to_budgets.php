<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->string('unit', 30)->nullable()->after('activity');
            $table->decimal('rate', 20, 2)->default(0)->after('unit');
        });

        Schema::table('budget_monthly_lines', function (Blueprint $table) {
            $table->decimal('quantity', 20, 2)->default(0)->after('month');
        });
    }

    public function down(): void
    {
        Schema::table('budget_monthly_lines', fn (Blueprint $table) => $table->dropColumn('quantity'));
        Schema::table('budgets', fn (Blueprint $table) => $table->dropColumn(['unit', 'rate']));
    }
};
