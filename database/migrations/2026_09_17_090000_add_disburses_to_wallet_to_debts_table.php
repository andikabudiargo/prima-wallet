<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            // Sebagian hutang bukan pencairan uang tunai (mis. cicilan barang/
            // kendaraan yang langsung diserahkan penjual), jadi tidak semua
            // hutang/piutang harus menambah/mengurangi saldo dompet saat dibuat.
            $table->boolean('disburses_to_wallet')->default(true)->after('wallet_id');
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn('disburses_to_wallet');
        });
    }
};
