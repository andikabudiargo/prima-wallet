<?php

namespace App\Services;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class ChecklistService
{
    /**
     * Buat checklist baru beserta item template-nya, lalu langsung mulai
     * sesi/run pertama supaya user bisa langsung pakai.
     */
    public function createChecklist(User $user, string $name, ?int $walletId, array $items): Checklist
    {
        return DB::transaction(function () use ($user, $name, $walletId, $items) {
            $checklist = Checklist::create([
                'user_id' => $user->id,
                'name' => $name,
                'wallet_id' => $walletId,
                'created_by' => $user->id,
            ]);

            foreach ($items as $index => $item) {
                ChecklistItem::create([
                    'checklist_id' => $checklist->id,
                    'category_id' => $item['category_id'],
                    'name' => $item['name'],
                    'target_price' => $item['target_price'],
                    'sort_order' => $index,
                ]);
            }

            $this->startNewRun($checklist->fresh());

            return $checklist;
        });
    }

    /**
     * Tutup sesi aktif (kalau ada) sebagai snapshot histori, lalu mulai sesi
     * baru dengan menyalin item dari template saat ini.
     */
    public function startNewRun(Checklist $checklist): ChecklistRun
    {
        return DB::transaction(function () use ($checklist) {
            $activeRun = $checklist->activeRun()->first();
            if ($activeRun) {
                $activeRun->update(['status' => 'completed', 'completed_at' => now()]);
            }

            $run = ChecklistRun::create([
                'checklist_id' => $checklist->id,
                'status' => 'active',
                'started_at' => now(),
            ]);

            foreach ($checklist->items as $index => $templateItem) {
                ChecklistRunItem::create([
                    'checklist_run_id' => $run->id,
                    'checklist_item_id' => $templateItem->id,
                    'category_id' => $templateItem->category_id,
                    'name' => $templateItem->name,
                    'target_price' => $templateItem->target_price,
                    'sort_order' => $index,
                ]);
            }

            return $run;
        });
    }

    /**
     * Tambah item baru ke template. Kalau ada sesi aktif, item ini juga
     * langsung muncul di sesi itu (belum dicentang) tanpa perlu sesi baru.
     */
    public function addTemplateItem(Checklist $checklist, string $name, int $categoryId, float $targetPrice): ChecklistItem
    {
        return DB::transaction(function () use ($checklist, $name, $categoryId, $targetPrice) {
            $sortOrder = $checklist->items()->count();

            $item = ChecklistItem::create([
                'checklist_id' => $checklist->id,
                'category_id' => $categoryId,
                'name' => $name,
                'target_price' => $targetPrice,
                'sort_order' => $sortOrder,
            ]);

            $activeRun = $checklist->activeRun()->first();
            if ($activeRun) {
                ChecklistRunItem::create([
                    'checklist_run_id' => $activeRun->id,
                    'checklist_item_id' => $item->id,
                    'category_id' => $categoryId,
                    'name' => $name,
                    'target_price' => $targetPrice,
                    'sort_order' => $activeRun->items()->count(),
                ]);
            }

            return $item;
        });
    }

    /**
     * Edit item template. Run item pasangannya di sesi aktif ikut disinkron
     * HANYA kalau belum dicentang - yang sudah dicentang tetap beku sebagai
     * histori transaksi yang sudah terjadi.
     */
    public function updateTemplateItem(ChecklistItem $item, string $name, int $categoryId, float $targetPrice): ChecklistItem
    {
        return DB::transaction(function () use ($item, $name, $categoryId, $targetPrice) {
            $item->update(['name' => $name, 'category_id' => $categoryId, 'target_price' => $targetPrice]);

            $activeRun = $item->checklist->activeRun()->first();
            if ($activeRun) {
                ChecklistRunItem::where('checklist_run_id', $activeRun->id)
                    ->where('checklist_item_id', $item->id)
                    ->where('is_checked', false)
                    ->update(['name' => $name, 'category_id' => $categoryId, 'target_price' => $targetPrice]);
            }

            return $item;
        });
    }

    /**
     * Hapus item template. Run item pasangannya di sesi aktif ikut dihapus
     * HANYA kalau belum dicentang.
     */
    public function deleteTemplateItem(ChecklistItem $item): void
    {
        DB::transaction(function () use ($item) {
            $activeRun = $item->checklist->activeRun()->first();
            if ($activeRun) {
                ChecklistRunItem::where('checklist_run_id', $activeRun->id)
                    ->where('checklist_item_id', $item->id)
                    ->where('is_checked', false)
                    ->delete();
            }

            $item->delete();
        });
    }

    /**
     * Centang item: catat harga aktual sebagai transaksi pengeluaran nyata
     * (memotong saldo dompet), supaya target vs actual bisa dibandingkan.
     */
    public function checkItem(ChecklistRunItem $runItem, float $actualPrice, int $walletId, User $user): Transaction
    {
        return DB::transaction(function () use ($runItem, $actualPrice, $walletId, $user) {
            $wallet = Wallet::findOrFail($walletId);

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'type' => 'expense',
                'category_id' => $runItem->category_id,
                'amount' => $actualPrice,
                'description' => $runItem->name,
                'transaction_date' => now()->toDateString(),
                'created_by' => $user->id,
                'checklist_run_item_id' => $runItem->id,
            ]);

            $wallet->decrement('balance', $actualPrice);

            $runItem->update([
                'actual_price' => $actualPrice,
                'is_checked' => true,
                'checked_at' => now(),
                'transaction_id' => $transaction->id,
            ]);

            return $transaction;
        });
    }

    /**
     * Batal centang: balikkan saldo dompet & hapus transaksi yang sempat
     * tercatat, lalu kembalikan item ke kondisi belum dicentang.
     */
    public function uncheckItem(ChecklistRunItem $runItem): void
    {
        DB::transaction(function () use ($runItem) {
            if ($runItem->transaction_id) {
                $transaction = Transaction::find($runItem->transaction_id);
                if ($transaction) {
                    $transaction->wallet->increment('balance', $transaction->amount);
                    $transaction->delete();
                }
            }

            $runItem->update([
                'actual_price' => null,
                'is_checked' => false,
                'checked_at' => null,
                'transaction_id' => null,
            ]);
        });
    }
}
