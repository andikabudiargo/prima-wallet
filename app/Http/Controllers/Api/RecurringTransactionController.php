<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RecurringTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = RecurringTransaction::where('user_id', $request->user()->id)
            ->with(['wallet', 'category']);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->orderBy('day_of_month')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_id' => 'required|exists:wallets,id',
            'type' => 'required|in:income,expense',
            'category_id' => 'required|exists:categories,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'day_of_month' => 'required|integer|min:1|max:31',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $wallet = Wallet::where('id', $request->wallet_id)->where('user_id', $request->user()->id)->first();
        if (! $wallet) {
            return response()->json(['message' => 'Dompet tidak ditemukan'], 404);
        }

        $category = Category::where('id', $request->category_id)
            ->where('type', $request->type)
            ->where(function ($q) use ($request) {
                $q->whereNull('user_id')->orWhere('user_id', $request->user()->id);
            })
            ->first();

        if (! $category) {
            return response()->json(['message' => 'Kategori tidak valid'], 422);
        }

        $recurring = RecurringTransaction::create([
            'user_id' => $request->user()->id,
            'wallet_id' => $wallet->id,
            'type' => $request->type,
            'category_id' => $category->id,
            'amount' => $request->amount,
            'description' => $request->description,
            'day_of_month' => $request->day_of_month,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($recurring->load(['wallet', 'category']), 201);
    }

    public function update(Request $request, RecurringTransaction $recurringTransaction)
    {
        if ($recurringTransaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'wallet_id' => 'sometimes|exists:wallets,id',
            'category_id' => 'sometimes|exists:categories,id',
            'amount' => 'sometimes|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'day_of_month' => 'sometimes|integer|min:1|max:31',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('wallet_id')) {
            $wallet = Wallet::where('id', $request->wallet_id)->where('user_id', $request->user()->id)->first();
            if (! $wallet) {
                return response()->json(['message' => 'Dompet tidak ditemukan'], 404);
            }
        }

        if ($request->has('category_id')) {
            $category = Category::where('id', $request->category_id)
                ->where('type', $recurringTransaction->type)
                ->where(function ($q) use ($request) {
                    $q->whereNull('user_id')->orWhere('user_id', $request->user()->id);
                })
                ->first();
            if (! $category) {
                return response()->json(['message' => 'Kategori tidak valid'], 422);
            }
        }

        $recurringTransaction->update($request->only([
            'wallet_id', 'category_id', 'amount', 'description', 'day_of_month', 'is_active',
        ]));

        return response()->json($recurringTransaction->load(['wallet', 'category']));
    }

    public function destroy(Request $request, RecurringTransaction $recurringTransaction)
    {
        if ($recurringTransaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $recurringTransaction->delete();

        return response()->json(['message' => 'Transaksi berulang dihapus']);
    }
}
