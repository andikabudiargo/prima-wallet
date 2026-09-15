<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringTransactionService
{
    /**
     * Dipanggil dari scheduled command harian: proses semua aturan
     * berulang yang aktif dan jatuh tempo hari ini, sekali per bulan.
     */
    public function processDue(): void
    {
        $today = Carbon::today();

        RecurringTransaction::where('is_active', true)->each(function (RecurringTransaction $recurring) use ($today) {
            $dueDay = min($recurring->day_of_month, $today->daysInMonth);
            if ($today->day !== $dueDay) {
                return;
            }

            $alreadyProcessed = Transaction::where('recurring_transaction_id', $recurring->id)
                ->whereYear('transaction_date', $today->year)
                ->whereMonth('transaction_date', $today->month)
                ->exists();

            if ($alreadyProcessed) {
                return;
            }

            $this->createTransactionFor($recurring, $today);
        });
    }

    private function createTransactionFor(RecurringTransaction $recurring, Carbon $date): void
    {
        DB::transaction(function () use ($recurring, $date) {
            $wallet = Wallet::find($recurring->wallet_id);
            if (! $wallet) {
                return;
            }

            Transaction::create([
                'user_id' => $recurring->user_id,
                'wallet_id' => $wallet->id,
                'type' => $recurring->type,
                'category_id' => $recurring->category_id,
                'amount' => $recurring->amount,
                'description' => $recurring->description,
                'transaction_date' => $date->toDateString(),
                'created_by' => $recurring->created_by,
                'recurring_transaction_id' => $recurring->id,
            ]);

            if ($recurring->type === 'income') {
                $wallet->increment('balance', $recurring->amount);
            } else {
                $wallet->decrement('balance', $recurring->amount);
            }
        });
    }
}
