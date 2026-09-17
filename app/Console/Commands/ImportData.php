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

        // TRUNCATE wajib di luar transaksi: di MySQL, TRUNCATE adalah DDL
        // yang otomatis commit begitu dijalankan (implicit commit) - kalau
        // dipanggil di dalam DB::transaction(), transaksi Laravel jadi
        // "tertutup diam-diam" dan commit() di akhir gagal dengan error
        // "There is no active transaction". Truncate semua tabel sekaligus
        // di awal (bukan satu-satu di dalam loop insert) supaya tabel yang
        // di-cascade (mis. transactions, direferensikan balik oleh
        // debt_id/checklist_run_item_id/recurring_transaction_id) tidak
        // ikut kehapus lagi belakangan setelah sempat di-insert.
        //
        // FOREIGN_KEY_CHECKS juga harus dimatikan SEBELUM truncate (bukan
        // cuma sebelum insert) - MySQL menolak truncate tabel yang masih
        // direferensikan tabel lain (mis. users <- wallets.created_by)
        // kalau FK check masih aktif.
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        if ($driver === 'pgsql') {
            $quoted = implode(', ', array_map(fn ($t) => "\"{$t}\"", $this->tables));
            DB::statement("TRUNCATE TABLE {$quoted} CASCADE");
        } else {
            foreach ($this->tables as $table) {
                DB::table($table)->truncate();
            }
        }

        $insertAll = function () use ($data) {
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
        };

        if ($driver === 'pgsql') {
            // Postgres menegakkan FK langsung saat INSERT (bukan cuma pas
            // commit) kecuali constraint-nya di-defer eksplisit di dalam
            // transaksi - makanya untuk Postgres tetap butuh pembungkus
            // transaksi supaya urutan insert antar tabel tidak jadi masalah.
            DB::transaction(function () use ($insertAll) {
                DB::statement('SET CONSTRAINTS ALL DEFERRED');
                $insertAll();
            });
        } else {
            // Untuk MySQL sengaja TIDAK dibungkus DB::transaction(): FK
            // checks sudah dimatikan manual di atas jadi urutan insert
            // sudah aman tanpa transaksi, dan beberapa koneksi MySQL
            // managed/remote (mis. cluster shared hosting) ternyata tidak
            // mempertahankan state transaksi dengan konsisten lintas query,
            // menyebabkan error "There is no active transaction" saat commit.
            $insertAll();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
