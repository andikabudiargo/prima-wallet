<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['type' => 'income', 'name' => 'Gaji', 'icon' => 'work_outline', 'color' => '#4CAF50'],
            ['type' => 'income', 'name' => 'Bonus', 'icon' => 'card_giftcard', 'color' => '#FF9800'],
            ['type' => 'income', 'name' => 'Hadiah', 'icon' => 'redeem', 'color' => '#E91E63'],
            ['type' => 'income', 'name' => 'Penjualan', 'icon' => 'sell', 'color' => '#2196F3'],
            ['type' => 'income', 'name' => 'Investasi', 'icon' => 'trending_up', 'color' => '#009688'],
            ['type' => 'income', 'name' => 'Lainnya', 'icon' => 'more_horiz', 'color' => '#607D8B'],

            ['type' => 'expense', 'name' => 'Makanan', 'icon' => 'restaurant', 'color' => '#FF5722'],
            ['type' => 'expense', 'name' => 'Transportasi', 'icon' => 'directions_car', 'color' => '#3F51B5'],
            ['type' => 'expense', 'name' => 'Belanja', 'icon' => 'shopping_bag', 'color' => '#9C27B0'],
            ['type' => 'expense', 'name' => 'Tagihan', 'icon' => 'receipt_long', 'color' => '#F44336'],
            ['type' => 'expense', 'name' => 'Hiburan', 'icon' => 'movie', 'color' => '#673AB7'],
            ['type' => 'expense', 'name' => 'Kesehatan', 'icon' => 'medical_services', 'color' => '#00BCD4'],
            ['type' => 'expense', 'name' => 'Pendidikan', 'icon' => 'school', 'color' => '#795548'],
            ['type' => 'expense', 'name' => 'Lainnya', 'icon' => 'more_horiz', 'color' => '#607D8B'],
        ];

        foreach ($defaults as $item) {
            Category::create($item); // user_id null = default/global
        }
    }
}