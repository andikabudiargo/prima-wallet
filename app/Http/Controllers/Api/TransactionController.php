<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    private array $categories = [
        'income' => ['gaji', 'bonus', 'hadiah', 'penjualan', 'investasi', 'lainnya'],
        'expense' => ['makanan', 'transportasi', 'belanja', 'tagihan', 'hiburan', 'kesehatan', 'pendidikan', 'lainnya'],
    ];

    public function index(Request $request)
    {
        $query = Transaction::with(['wallet', 'category'])
            ->where('user_id', $request->user()->id);

        if ($request->has('type')) {
            $query->where('type', $request->type); // ?type=income atau ?type=expense
        }

        $transactions = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($transactions);
    }

    public function store(Request $request)
{
    $type = $request->input('type');

    $validator = Validator::make($request->all(), [
        'wallet_id' => 'required|exists:wallets,id',
        'type' => 'required|in:income,expense',
        'category_id' => 'required|exists:categories,id',
        'amount' => 'required|numeric|min:0.01',
        'description' => 'nullable|string|max:255',
        'transaction_date' => 'required|date',
        'evidence' => 'nullable|image|max:5120',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $wallet = Wallet::where('id', $request->wallet_id)
        ->where('user_id', $request->user()->id)
        ->first();

    if (!$wallet) {
        return response()->json(['message' => 'Dompet tidak ditemukan'], 404);
    }

    // pastikan kategori memang milik tipe yang sesuai & bisa diakses user ini
    $category = \App\Models\Category::where('id', $request->category_id)
        ->where('type', $type)
        ->where(function ($q) use ($request) {
            $q->whereNull('user_id')->orWhere('user_id', $request->user()->id);
        })
        ->first();

    if (!$category) {
        return response()->json(['message' => 'Kategori tidak valid'], 422);
    }

    $evidencePath = null;
    if ($request->hasFile('evidence')) {
        $evidencePath = $request->file('evidence')->store('transaction-evidence', 'public');
    }

    $transaction = DB::transaction(function () use ($request, $wallet, $evidencePath, $type, $category) {
        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'wallet_id' => $wallet->id,
            'type' => $type,
            'category_id' => $category->id,
            'amount' => $request->amount,
            'description' => $request->description,
            'evidence_path' => $evidencePath,
            'transaction_date' => $request->transaction_date,
            'created_by' => $request->user()->id,
        ]);

        if ($type === 'income') {
            $wallet->increment('balance', $request->amount);
        } else {
            $wallet->decrement('balance', $request->amount);
        }

        return $transaction;
    });

    return response()->json($transaction->load(['wallet', 'category']), 201);
}

    public function show(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($transaction->load(['wallet', 'category']));
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::transaction(function () use ($transaction) {
            if ($transaction->type === 'income') {
                $transaction->wallet->decrement('balance', $transaction->amount);
            } else {
                $transaction->wallet->increment('balance', $transaction->amount);
            }
            $transaction->delete();
        });

        return response()->json(['message' => 'Transaksi dihapus']);
    }
}