<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('address')->nullable()->after('code');
            $table->string('phone', 50)->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
            $table->string('invoice_bank_name')->nullable()->after('email');
            $table->string('invoice_bank_account_name')->nullable()->after('invoice_bank_name');
            $table->string('invoice_bank_account_number', 100)->nullable()->after('invoice_bank_account_name');
            $table->decimal('default_vat_rate', 5, 2)->default(0)->after('invoice_bank_account_number');
            $table->decimal('default_pph23_rate', 5, 2)->default(0)->after('default_vat_rate');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'address', 'phone', 'email', 'invoice_bank_name', 'invoice_bank_account_name',
                'invoice_bank_account_number', 'default_vat_rate', 'default_pph23_rate',
            ]);
        });
    }
};
