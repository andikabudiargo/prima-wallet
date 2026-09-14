<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('debt_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->foreignId('debt_installment_id')->nullable()->after('debt_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('debt_installment_id');
            $table->dropConstrainedForeignId('debt_id');
        });
    }
};
