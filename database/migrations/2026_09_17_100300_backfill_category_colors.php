<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Isi warna kategori bawaan yang sudah lebih dulu ada di database
     * (dibuat sebelum kolom `color` dipakai) supaya ikonnya langsung
     * berwarna tanpa perlu diedit manual satu-satu.
     */
    public function up(): void
    {
        $colors = [
            'Gaji' => '#4CAF50',
            'Bonus' => '#FF9800',
            'Hadiah' => '#E91E63',
            'Penjualan' => '#2196F3',
            'Investasi' => '#009688',
            'Makanan' => '#FF5722',
            'Transportasi' => '#3F51B5',
            'Belanja' => '#9C27B0',
            'Tagihan' => '#F44336',
            'Hiburan' => '#673AB7',
            'Kesehatan' => '#00BCD4',
            'Pendidikan' => '#795548',
            'Lainnya' => '#607D8B',
        ];

        foreach ($colors as $name => $color) {
            DB::table('categories')
                ->where('name', $name)
                ->whereNull('color')
                ->update(['color' => $color]);
        }
    }

    public function down(): void
    {
        // Tidak perlu dikembalikan - kolom warna tetap boleh terisi.
    }
};
