<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // "Prima ID" - semacam username unik, alternatif email buat login.
            // Nullable karena user lama/baru belum wajib set ini saat daftar.
            $table->string('prima_id')->nullable()->unique()->after('email');
            $table->string('avatar_path')->nullable()->after('business_logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['prima_id', 'avatar_path']);
        });
    }
};
