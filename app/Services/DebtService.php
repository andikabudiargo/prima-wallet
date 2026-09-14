<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtInstallment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DebtService
{
    /**
     * Bangun jadwal cicilan awal (baris-baris debt_installments). Rumus bunga
     * hanya dipakai untuk mengisi nilai default ini - setelah dibuat, tiap
     * baris adalah data biasa yang bisa diedit lewat pembayaran manual.
     */
    public function generateSchedule(Debt $debt): void
    {
        $principal = (float) $debt->principal_amount;
        $tenor = $debt->tenor_months;
        $rate = (float) ($debt->interest_rate ?? 0);

        $rows = [];

        if ($debt->interest_type === 'declining') {
            $remaining = $principal;
            $principalPortion = round($principal / $tenor);
            $allocatedPrincipal = 0;

            for ($i = 1; $i <= $tenor; $i++) {
                $isLast = $i === $tenor;
                $portion = $isLast ? round($principal - $allocatedPrincipal) : $principalPortion;
                $interest = round($remaining * $rate / 100);
                $amountDue = $portion + $interest;

                $rows[] = [
                    'installment_number' => $i,
                    'due_date' => $this->dueDateFor($debt, $i),
                    'amount_due' => $amountDue,
                ];

                $remaining -= $portion;
                $allocatedPrincipal += $portion;
            }
        } else {
            $totalInterest = $debt->interest_type === 'flat' ? round($principal * $rate / 100 * $tenor) : 0;
            $total = $principal + $totalInterest;
            $base = round($total / $tenor);
            $allocated = 0;

            for ($i = 1; $i <= $tenor; $i++) {
                $isLast = $i === $tenor;
                $amountDue = $isLast ? round($total - $allocated) : $base;

                $rows[] = [
                    'installment_number' => $i,
                    'due_date' => $this->dueDateFor($debt, $i),
                    'amount_due' => $amountDue,
                ];

                $allocated += $amountDue;
            }
        }

        foreach ($rows as $row) {
            $debt->installments()->create([
                'installment_number' => $row['installment_number'],
                'due_date' => $row['due_date'],
                'amount_due' => $row['amount_due'],
                'amount_paid' => 0,
                'status' => 'upcoming',
            ]);
        }

        $debt->total_amount = array_sum(array_column($rows, 'amount_due'));
        $debt->save();
    }

    private function dueDateFor(Debt $debt, int $installmentNumber): Carbon
    {
        $target = Carbon::parse($debt->start_date)->addMonthsNoOverflow($installmentNumber);

        return $target->day(min($debt->due_day, $target->daysInMonth));
    }

    /**
     * Catat pencairan pokok hutang/piutang sebagai transaksi kas (dipanggil
     * sekali saat hutang/piutang baru dibuat), supaya saldo dompet konsisten
     * dengan uang yang benar-benar cair/dipinjamkan.
     */
    public function disburse(Debt $debt): Transaction
    {
        $isHutang = $debt->type === 'hutang';
        $categoryType = $isHutang ? 'income' : 'expense';
        $category = $this->resolveCategory($isHutang ? 'Pencairan Hutang' : 'Piutang Diberikan', $categoryType);
        $wallet = Wallet::findOrFail($debt->wallet_id);

        $transaction = Transaction::create([
            'user_id' => $debt->user_id,
            'wallet_id' => $wallet->id,
            'type' => $categoryType,
            'category_id' => $category->id,
            'amount' => $debt->principal_amount,
            'description' => ($isHutang ? 'Pencairan hutang - ' : 'Dana dipinjamkan - ').$debt->party_name,
            'transaction_date' => $debt->start_date,
            'created_by' => $debt->created_by,
            'debt_id' => $debt->id,
        ]);

        if ($categoryType === 'income') {
            $wallet->increment('balance', $debt->principal_amount);
        } else {
            $wallet->decrement('balance', $debt->principal_amount);
        }

        return $transaction;
    }

    /**
     * Bayar satu cicilan. Nominal boleh kurang (sisa menumpuk di cicilan yang
     * sama) atau lebih (kelebihan otomatis jadi pembayaran di muka untuk
     * cicilan berikutnya).
     */
    public function payInstallment(DebtInstallment $installment, float $amount, int $walletId, User $user): Transaction
    {
        return DB::transaction(function () use ($installment, $amount, $walletId, $user) {
            $debt = $installment->debt;
            $isHutang = $debt->type === 'hutang';
            $categoryType = $isHutang ? 'expense' : 'income';
            $category = $this->resolveCategory($isHutang ? 'Cicilan Hutang' : 'Cicilan Piutang', $categoryType);
            $wallet = Wallet::findOrFail($walletId);

            $transaction = Transaction::create([
                'user_id' => $debt->user_id,
                'wallet_id' => $wallet->id,
                'type' => $categoryType,
                'category_id' => $category->id,
                'amount' => $amount,
                'description' => "Cicilan ke-{$installment->installment_number} - {$debt->party_name}",
                'transaction_date' => now()->toDateString(),
                'created_by' => $user->id,
                'debt_id' => $debt->id,
                'debt_installment_id' => $installment->id,
            ]);

            if ($categoryType === 'expense') {
                $wallet->decrement('balance', $amount);
            } else {
                $wallet->increment('balance', $amount);
            }

            $this->applyPayment($installment, $amount);

            $debt->paid_amount = $debt->installments()->sum('amount_paid');
            if ($debt->installments()->where('status', '!=', 'paid')->doesntExist()) {
                $debt->status = 'paid_off';
            }
            $debt->save();

            return $transaction;
        });
    }

    /**
     * Terapkan pembayaran ke satu baris cicilan, lalu limpahkan kelebihannya
     * (kalau ada) ke cicilan upcoming berikutnya secara berantai.
     */
    private function applyPayment(DebtInstallment $installment, float $amount): void
    {
        $newPaid = round((float) $installment->amount_paid + $amount, 2);
        $due = (float) $installment->amount_due;

        if ($newPaid >= $due) {
            $overflow = round($newPaid - $due, 2);
            $installment->amount_paid = $due;
            $installment->status = 'paid';
            $installment->paid_at = now();
            $installment->save();

            if ($overflow > 0) {
                $next = DebtInstallment::where('debt_id', $installment->debt_id)
                    ->where('status', 'upcoming')
                    ->where('installment_number', '>', $installment->installment_number)
                    ->orderBy('installment_number')
                    ->first();

                if ($next) {
                    $this->applyPayment($next, $overflow);
                }
                // kalau tidak ada cicilan berikutnya, kelebihan tetap tercatat
                // sebagai transaksi kas tapi tidak dialokasikan ke cicilan manapun.
            }
        } else {
            $installment->amount_paid = $newPaid;
            $installment->save();
        }
    }

    /**
     * Lunasi sisa hutang/piutang sekaligus dalam satu transaksi.
     */
    public function payOff(Debt $debt, int $walletId, User $user): Transaction
    {
        return DB::transaction(function () use ($debt, $walletId, $user) {
            $remaining = round((float) $debt->total_amount - (float) $debt->paid_amount, 2);

            if ($remaining <= 0) {
                throw new \RuntimeException('Hutang ini sudah lunas.');
            }

            $isHutang = $debt->type === 'hutang';
            $categoryType = $isHutang ? 'expense' : 'income';
            $category = $this->resolveCategory($isHutang ? 'Cicilan Hutang' : 'Cicilan Piutang', $categoryType);
            $wallet = Wallet::findOrFail($walletId);

            $transaction = Transaction::create([
                'user_id' => $debt->user_id,
                'wallet_id' => $wallet->id,
                'type' => $categoryType,
                'category_id' => $category->id,
                'amount' => $remaining,
                'description' => ($isHutang ? 'Pelunasan hutang - ' : 'Pelunasan piutang - ').$debt->party_name,
                'transaction_date' => now()->toDateString(),
                'created_by' => $user->id,
                'debt_id' => $debt->id,
            ]);

            if ($categoryType === 'expense') {
                $wallet->decrement('balance', $remaining);
            } else {
                $wallet->increment('balance', $remaining);
            }

            DebtInstallment::where('debt_id', $debt->id)
                ->where('status', '!=', 'paid')
                ->get()
                ->each(fn (DebtInstallment $installment) => $installment->update([
                    'amount_paid' => $installment->amount_due,
                    'status' => 'paid',
                    'paid_at' => now(),
                ]));

            $debt->paid_amount = $debt->total_amount;
            $debt->status = 'paid_off';
            $debt->save();

            return $transaction;
        });
    }

    /**
     * Dipanggil dari scheduled command harian: tandai cicilan yang telat
     * sebagai overdue, lalu proses auto-debet untuk hutang yang mengaktifkannya.
     */
    public function runAutoDebet(): void
    {
        $today = now()->toDateString();

        DebtInstallment::where('status', 'upcoming')
            ->where('due_date', '<', $today)
            ->update(['status' => 'overdue']);

        $dueInstallments = DebtInstallment::whereIn('status', ['upcoming', 'overdue'])
            ->where('due_date', '<=', $today)
            ->whereHas('debt', fn ($q) => $q->where('auto_debet', true)->where('status', 'active'))
            ->with(['debt.autoWallet', 'debt.user'])
            ->get();

        foreach ($dueInstallments as $installment) {
            $debt = $installment->debt;
            $remaining = round((float) $installment->amount_due - (float) $installment->amount_paid, 2);

            if ($remaining <= 0) {
                continue;
            }

            $wallet = $debt->autoWallet;
            if (! $wallet || (float) $wallet->balance < $remaining) {
                continue; // saldo tidak cukup, biarkan overdue
            }

            $this->payInstallment($installment, $remaining, $wallet->id, $debt->user);
        }
    }

    private function resolveCategory(string $name, string $type): Category
    {
        return Category::where('name', $name)->where('type', $type)->whereNull('user_id')->firstOrFail();
    }
}
