<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Export semua data pengguna (bukan tabel sistem seperti sessions/cache/jobs)
 * ke satu file JSON portable - dipakai buat pindah database antar driver
 * berbeda (mis. Postgres lokal -> MySQL hosting) tanpa masalah dialek SQL.
 */
class ExportData extends Command
{
    protected $signature = 'data:export {--path=storage/app/data-export.json}';

    protected $description = 'Export semua data ke file JSON buat dipindah ke database lain';

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
        $data = [];

        foreach ($this->tables as $table) {
            $rows = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            $data[$table] = $rows;
            $this->info(sprintf('%-24s %d baris', $table, count($rows)));
        }

        $path = base_path($this->option('path'));
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info("Tersimpan di: {$path}");

        return self::SUCCESS;
    }
}
