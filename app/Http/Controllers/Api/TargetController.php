<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TargetController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $targets = Target::where('user_id', $userId)
            ->with(['wallet', 'contributions'])
            ->orderByRaw('deadline IS NULL, deadline asc')
            ->get();

        $totalLiquid = Wallet::where('user_id', $userId)->where('is_active', true)->sum('balance');

        return response()->json($targets->map(fn ($target) => $this->present($target, $targets, $totalLiquid)));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'target_amount' => 'required|numeric|min:0.01',
            'wallet_id' => 'nullable|exists:wallets,id',
            'deadline' => 'nullable|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->filled('wallet_id')) {
            $wallet = Wallet::where('id', $request->wallet_id)->where('user_id', $request->user()->id)->first();
            if (! $wallet) {
                return response()->json(['message' => 'Dompet tidak ditemukan'], 404);
            }
        }

        $target = Target::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'target_amount' => $request->target_amount,
            'wallet_id' => $request->wallet_id,
            'deadline' => $request->deadline,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($target->load(['wallet', 'contributions']), 201);
    }

    public function update(Request $request, Target $target)
    {
        if ($target->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'target_amount' => 'sometimes|numeric|min:0.01',
            'wallet_id' => 'nullable|exists:wallets,id',
            'deadline' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->filled('wallet_id')) {
            $wallet = Wallet::where('id', $request->wallet_id)->where('user_id', $request->user()->id)->first();
            if (! $wallet) {
                return response()->json(['message' => 'Dompet tidak ditemukan'], 404);
            }
        }

        $target->update($request->only(['name', 'target_amount', 'wallet_id', 'deadline']));

        return response()->json($target->load(['wallet', 'contributions']));
    }

    public function destroy(Request $request, Target $target)
    {
        if ($target->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $target->delete();

        return response()->json(['message' => 'Target dihapus']);
    }

    public function addContribution(Request $request, Target $target)
    {
        if ($target->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:255',
            'contribution_date' => 'nullable|date|before_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $target->contributions()->create([
            'amount' => $request->amount,
            'note' => $request->note,
            'contribution_date' => $request->contribution_date ?? now()->toDateString(),
            'created_by' => $request->user()->id,
        ]);

        $userId = $request->user()->id;
        $allTargets = Target::where('user_id', $userId)->with('contributions')->get();
        $totalLiquid = Wallet::where('user_id', $userId)->where('is_active', true)->sum('balance');

        return response()->json($this->present($target->load(['wallet', 'contributions']), $allTargets, $totalLiquid), 201);
    }

    public function deleteContribution(Request $request, \App\Models\TargetContribution $contribution)
    {
        if ($contribution->target->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $contribution->delete();

        return response()->json(['message' => 'Kontribusi dihapus']);
    }

    /**
     * Susun payload target lengkap dengan progres & kapasitas beli.
     * Kapasitas beli = likuiditas yang relevan (saldo dompet spesifik, atau
     * akumulasi seluruh dompet kalau tidak diassign) dikurangi saved_amount
     * target aktif lain yang berebut sumber likuiditas sama.
     */
    private function present(Target $target, $allTargets, float $totalLiquid): array
    {
        $savedAmount = $target->contributions->sum('amount');

        if ($target->wallet_id) {
            $available = (float) $target->wallet->balance;
            $lockedByOthers = $allTargets
                ->where('wallet_id', $target->wallet_id)
                ->where('id', '!=', $target->id)
                ->sum(fn ($t) => $t->contributions->sum('amount'));
        } else {
            $available = $totalLiquid;
            $lockedByOthers = $allTargets
                ->whereNull('wallet_id')
                ->where('id', '!=', $target->id)
                ->sum(fn ($t) => $t->contributions->sum('amount'));
        }

        $remainingCapacity = round($available - $lockedByOthers, 2);
        $progress = $target->target_amount > 0 ? min(1, $savedAmount / $target->target_amount) : 0;

        return [
            'id' => $target->id,
            'name' => $target->name,
            'target_amount' => $target->target_amount,
            'wallet' => $target->wallet,
            'deadline' => $target->deadline?->toDateString(),
            'saved_amount' => round($savedAmount, 2),
            'remaining_amount' => round(max(0, $target->target_amount - $savedAmount), 2),
            'progress' => round($progress, 4),
            'is_achieved' => $savedAmount >= $target->target_amount,
            'remaining_capacity' => $remainingCapacity,
            'contributions' => $target->contributions,
            'created_at' => $target->created_at,
        ];
    }
}
