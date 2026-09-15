<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import file JSON hasil `data:export` ke database yang sedang dipakai.
 * Jalankan SETELAH `php artisan migrate --force` di database tujuan (biar
 * skema tabel sudah ada). ID asli dipertahankan supaya semua relasi
 * (wallet_id, category_id, dst) tetap benar.
 */
class ImportData extends Command
{
    protected $signature = 'data:import {--path=storage/app/data-export.json}';

    protected $description = 'Import file JSON hasil data:export ke database saat ini';

    protected array $tables = [
        'users', 'wallets', 'categories', 'transactions',
        'debts', 'debt_installments',
        'budgets', 'income_estimates',
        'checklists', 'checklist_items', 'checklist_runs', 'checklist_run_items',
        'transfers', 'recurring_transactions',
        'assets', 'targets', 'target_contributions',
    ];

    public function handle(): int
    {
        $path = base_path($this->option('path'));

        if (! file_exists($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($path), true);
        $driver = DB::connection()->getDriverName();

        DB::transaction(function () use ($data, $driver) {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            } elseif ($driver === 'pgsql') {
                DB::statement('SET CONSTRAINTS ALL DEFERRED');
            }

            // Truncate SEMUA tabel dalam satu operasi di awal (bukan
            // per-tabel di dalam loop) - beberapa tabel di awal daftar
            // (mis. transactions) punya kolom nullable yang direferensikan
            // balik oleh tabel belakangan (debt_id, checklist_run_item_id,
            // recurring_transaction_id). TRUNCATE ... CASCADE per-tabel di
            // tengah loop akan ikut menghapus data yang baru saja di-insert
            // ke transactions begitu giliran truncate debts/checklists/dst
            // tiba - makanya harus dikosongkan semua dulu sebelum insert
            // apa pun dimulai.
            if ($driver === 'pgsql') {
                $quoted = implode(', ', array_map(fn ($t) => "\"{$t}\"", $this->tables));
                DB::statement("TRUNCATE TABLE {$quoted} CASCADE");
            } else {
                foreach ($this->tables as $table) {
                    DB::table($table)->truncate();
                }
            }

            foreach ($this->tables as $table) {
                $rows = $data[$table] ?? [];
                if (empty($rows)) {
                    continue;
                }

                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }

                $this->info(sprintf('%-24s %d baris di-import', $table, count($rows)));
            }

            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        $this->newLine();
        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
