<?php

namespace App\Console\Commands;

use App\Services\RecurringTransactionService;
use Illuminate\Console\Command;

class ProcessRecurringTransactions extends Command
{
    protected $signature = 'recurring-transactions:process';

    protected $description = 'Buat transaksi otomatis untuk aturan pemasukan/pengeluaran berulang yang jatuh tempo hari ini';

    public function handle(RecurringTransactionService $service): int
    {
        $service->processDue();
        $this->info('Pemrosesan transaksi berulang selesai.');

        return self::SUCCESS;
    }
}
