<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Pemasukan bisa dikaitkan ke invoice yang sudah dibuat, supaya
            // status pembayaran invoice (lunas/sebagian/belum) bisa dihitung
            // otomatis dari transaksi-transaksi yang mereferensikannya.
            $table->foreignId('invoice_id')->nullable()->after('debt_installment_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
