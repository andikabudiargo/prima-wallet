<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Dipakai sebagai kop invoice (dan nanti struk lain) - "diisi
            // sekali di preferences, dipakai berulang setiap generate invoice.
            $table->string('business_name')->nullable()->after('email');
            $table->string('business_tagline')->nullable()->after('business_name');
            $table->string('business_address')->nullable()->after('business_tagline');
            $table->string('business_phone')->nullable()->after('business_address');
            $table->string('business_logo_path')->nullable()->after('business_phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'business_name', 'business_tagline', 'business_address',
                'business_phone', 'business_logo_path',
            ]);
        });
    }
};
