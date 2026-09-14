<?php

namespace App\Console\Commands;

use App\Services\DebtService;
use Illuminate\Console\Command;

class ProcessDebtInstallments extends Command
{
    protected $signature = 'debts:process';

    protected $description = 'Tandai cicilan yang telat sebagai overdue dan proses auto-debet hutang yang mengaktifkannya';

    public function handle(DebtService $debtService): int
    {
        $debtService->runAutoDebet();
        $this->info('Pemrosesan cicilan hutang selesai.');

        return self::SUCCESS;
    }
}
