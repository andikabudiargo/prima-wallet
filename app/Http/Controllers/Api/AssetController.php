<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Debt;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $wallets = Wallet::where('user_id', $userId)->where('is_active', true)->orderBy('sort_order')->get();
        $assets = Asset::where('user_id', $userId)->orderByDesc('purchase_date')->get();

        $walletsTotal = $wallets->sum('balance');
        $assetsTotal = $assets->sum('purchase_price');
        $asetRiil = $walletsTotal + $assetsTotal;

        $hutangOutstanding = Debt::where('user_id', $userId)
            ->where('type', 'hutang')
            ->where('status', 'active')
            ->get()
            ->sum(fn ($debt) => $debt->remaining_amount);

        $piutangOutstanding = Debt::where('user_id', $userId)
            ->where('type', 'piutang')
            ->where('status', 'active')
            ->get()
            ->sum(fn ($debt) => $debt->remaining_amount);

        $netWorth = $asetRiil + $piutangOutstanding - $hutangOutstanding;

        return response()->json([
            'summary' => [
                'wallets_total' => round($walletsTotal, 2),
                'assets_total' => round($assetsTotal, 2),
                'aset_riil' => round($asetRiil, 2),
                'hutang_outstanding' => round($hutangOutstanding, 2),
                'piutang_outstanding' => round($piutangOutstanding, 2),
                'net_worth' => round($netWorth, 2),
            ],
            'wallets' => $wallets->map(fn ($w) => [
                'id' => $w->id,
                'name' => $w->name,
                'category' => $w->category,
                'balance' => $w->balance,
            ]),
            'assets' => $assets,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'purchase_price' => 'required|numeric|min:0.01',
            'purchase_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asset = Asset::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'category' => $request->category,
            'purchase_price' => $request->purchase_price,
            'purchase_date' => $request->purchase_date,
            'notes' => $request->notes,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($asset, 201);
    }

    public function destroy(Request $request, Asset $asset)
    {
        if ($asset->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $asset->delete();

        return response()->json(['message' => 'Aset dihapus']);
    }
}
