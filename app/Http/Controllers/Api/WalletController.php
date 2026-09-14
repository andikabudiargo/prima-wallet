<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $wallets = Wallet::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return response()->json($wallets);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category' => 'required|in:cash,bank,e_wallet,credit_card,investment,other',
            'balance' => 'nullable|numeric',
            'reference' => 'nullable|string|max:255',
            'is_primary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $request->user()->id;
        $balance = $request->input('balance', 0);
        $isPrimary = $request->boolean('is_primary');

        // Kalau ini wallet pertama user, otomatis jadi primary
        $hasAnyWallet = Wallet::where('user_id', $userId)->exists();
        if (!$hasAnyWallet) {
            $isPrimary = true;
        }

        if ($isPrimary) {
            Wallet::where('user_id', $userId)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $wallet = Wallet::create([
            'user_id' => $userId,
            'created_by' => $userId,
            'code' => 'WLT-' . strtoupper(uniqid()),
            'category' => $request->input('category'),
            'name' => $request->input('name'),
            'reference' => $request->input('reference'),
            'initial_balance' => $balance,
            'balance' => $balance,
            'is_primary' => $isPrimary,
        ]);

        return response()->json($wallet, 201);
    }

    public function show(Request $request, Wallet $wallet)
    {
        if ($wallet->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($wallet);
    }

    public function update(Request $request, Wallet $wallet)
    {
        if ($wallet->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|in:cash,bank,e_wallet,credit_card,investment,other',
            'reference' => 'nullable|string|max:255',
            'is_primary' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('is_primary') && $request->boolean('is_primary')) {
            Wallet::where('user_id', $request->user()->id)
                ->where('id', '!=', $wallet->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $wallet->update($request->only(['name', 'category', 'reference', 'is_primary', 'is_active']));

        return response()->json($wallet);
    }

    public function destroy(Request $request, Wallet $wallet)
    {
        if ($wallet->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $wallet->delete();

        return response()->json(['message' => 'Wallet dihapus']);
    }
}