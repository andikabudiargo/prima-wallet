<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['type' => 'income', 'name' => 'Gaji', 'icon' => 'work_outline'],
            ['type' => 'income', 'name' => 'Bonus', 'icon' => 'card_giftcard'],
            ['type' => 'income', 'name' => 'Hadiah', 'icon' => 'redeem'],
            ['type' => 'income', 'name' => 'Penjualan', 'icon' => 'sell'],
            ['type' => 'income', 'name' => 'Investasi', 'icon' => 'trending_up'],
            ['type' => 'income', 'name' => 'Lainnya', 'icon' => 'more_horiz'],

            ['type' => 'expense', 'name' => 'Makanan', 'icon' => 'restaurant'],
            ['type' => 'expense', 'name' => 'Transportasi', 'icon' => 'directions_car'],
            ['type' => 'expense', 'name' => 'Belanja', 'icon' => 'shopping_bag'],
            ['type' => 'expense', 'name' => 'Tagihan', 'icon' => 'receipt_long'],
            ['type' => 'expense', 'name' => 'Hiburan', 'icon' => 'movie'],
            ['type' => 'expense', 'name' => 'Kesehatan', 'icon' => 'medical_services'],
            ['type' => 'expense', 'name' => 'Pendidikan', 'icon' => 'school'],
            ['type' => 'expense', 'name' => 'Lainnya', 'icon' => 'more_horiz'],
        ];

        foreach ($defaults as $item) {
            Category::create($item); // user_id null = default/global
        }
    }
}