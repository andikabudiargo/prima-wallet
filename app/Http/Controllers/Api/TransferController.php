<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransferController extends Controller
{
    public function index(Request $request)
    {
        $transfers = Transfer::where('user_id', $request->user()->id)
            ->with(['fromWallet', 'toWallet'])
            ->orderBy('transfer_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($transfers);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_wallet_id' => 'required|exists:wallets,id|different:to_wallet_id',
            'to_wallet_id' => 'required|exists:wallets,id',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:255',
            'transfer_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fromWallet = Wallet::where('id', $request->from_wallet_id)
            ->where('user_id', $request->user()->id)
            ->first();
        $toWallet = Wallet::where('id', $request->to_wallet_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $fromWallet || ! $toWallet) {
            return response()->json(['message' => 'Dompet tidak ditemukan'], 404);
        }

        if ($fromWallet->currency !== $toWallet->currency) {
            return response()->json([
                'message' => 'Transfer antar dompet dengan mata uang berbeda belum didukung',
            ], 422);
        }

        if ((float) $fromWallet->balance < (float) $request->amount) {
            return response()->json(['message' => 'Saldo dompet asal tidak cukup'], 422);
        }

        $transfer = DB::transaction(function () use ($request, $fromWallet, $toWallet) {
            $fromWallet->decrement('balance', $request->amount);
            $toWallet->increment('balance', $request->amount);

            return Transfer::create([
                'user_id' => $request->user()->id,
                'from_wallet_id' => $fromWallet->id,
                'to_wallet_id' => $toWallet->id,
                'amount' => $request->amount,
                'note' => $request->note,
                'transfer_date' => $request->transfer_date,
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json($transfer->load(['fromWallet', 'toWallet']), 201);
    }

    public function destroy(Request $request, Transfer $transfer)
    {
        if ($transfer->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::transaction(function () use ($transfer) {
            $transfer->fromWallet->increment('balance', $transfer->amount);
            $transfer->toWallet->decrement('balance', $transfer->amount);
            $transfer->delete();
        });

        return response()->json(['message' => 'Transfer dihapus']);
    }
}
