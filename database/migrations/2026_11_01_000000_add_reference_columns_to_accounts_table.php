<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // PRD 02-SYSTEM "Master Akun": Kode | Nama | Modul | Jenis | Definisi
            $table->string('module')->nullable()->after('type');
            $table->text('definition')->nullable()->after('module');
            // Contra accounts (e.g. accumulated depreciation, prive) carry a negative position.
            $table->boolean('is_contra')->default(false)->after('definition');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['module', 'definition', 'is_contra']);
        });
    }
};
