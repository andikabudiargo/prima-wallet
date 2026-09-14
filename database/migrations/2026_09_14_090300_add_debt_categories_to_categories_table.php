<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $categories = [
        ['type' => 'income', 'name' => 'Pencairan Hutang', 'icon' => 'account_balance'],
        ['type' => 'expense', 'name' => 'Cicilan Hutang', 'icon' => 'payments'],
        ['type' => 'expense', 'name' => 'Piutang Diberikan', 'icon' => 'volunteer_activism'],
        ['type' => 'income', 'name' => 'Cicilan Piutang', 'icon' => 'account_balance_wallet'],
    ];

    public function up(): void
    {
        $now = now();
        foreach ($this->categories as $category) {
            DB::table('categories')->insert([
                'user_id' => null,
                'type' => $category['type'],
                'name' => $category['name'],
                'icon' => $category['icon'],
                'color' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->categories as $category) {
            DB::table('categories')
                ->whereNull('user_id')
                ->where('type', $category['type'])
                ->where('name', $category['name'])
                ->delete();
        }
    }
};
