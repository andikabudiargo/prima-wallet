<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\DebtInstallment;
use App\Services\DebtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DebtController extends Controller
{
    public function __construct(private DebtService $debtService)
    {
    }

    public function index(Request $request)
    {
        $query = Debt::with(['wallet', 'installments'])
            ->where('user_id', $request->user()->id);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $isHutang = $request->input('type') === 'hutang';

        // disburses_to_wallet default true supaya klien lama (yang belum kirim
        // field ini) tetap berperilaku seperti sebelumnya: pencairan dana penuh.
        $disbursesToWallet = $request->has('disburses_to_wallet')
            ? $request->boolean('disburses_to_wallet')
            : true;

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:hutang,piutang',
            'party_name' => 'required|string|max:150',
            'principal_amount' => 'required|numeric|min:1',
            'interest_type' => 'required|in:none,flat,declining',
            'interest_rate' => 'required_if:interest_type,flat,declining|nullable|numeric|min:0|max:100',
            'tenor_months' => 'required|integer|min:1|max:360',
            'start_date' => 'required|date',
            'due_day' => 'required|integer|min:1|max:31',
            'disburses_to_wallet' => 'nullable|boolean',
            'wallet_id' => $disbursesToWallet ? 'required|exists:wallets,id' : 'nullable|exists:wallets,id',
            'auto_debet' => 'nullable|boolean',
            'auto_wallet_id' => 'required_if:auto_debet,1,true|nullable|exists:wallets,id',
            'notes' => 'nullable|string|max:255',
            'evidence' => 'nullable|image|max:5120',
            // nomor cicilan yang sudah lunas di dunia nyata sebelum dicatat di
            // app (mis. hutang dicatat setelah jalan 3 bulan) - ditandai lunas
            // tanpa transaksi kas/pengaruh saldo dompet.
            'paid_installments' => 'nullable|array',
            'paid_installments.*' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $wallet = null;
        if ($disbursesToWallet) {
            $wallet = \App\Models\Wallet::where('id', $request->wallet_id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $wallet) {
                return response()->json(['message' => 'Dompet tidak ditemukan'], 404);
            }
        }

        $autoDebet = $isHutang && $request->boolean('auto_debet');

        if ($autoDebet) {
            $autoWallet = \App\Models\Wallet::where('id', $request->auto_wallet_id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $autoWallet) {
                return response()->json(['message' => 'Dompet auto-debet tidak ditemukan'], 404);
            }
        }

        $evidencePath = null;
        if ($request->hasFile('evidence')) {
            $evidencePath = $request->file('evidence')->store('debt-evidence', 'public');
        }

        $debt = DB::transaction(function () use ($request, $wallet, $disbursesToWallet, $autoDebet, $evidencePath) {
            $debt = Debt::create([
                'user_id' => $request->user()->id,
                'type' => $request->type,
                'party_name' => $request->party_name,
                'principal_amount' => $request->principal_amount,
                'interest_type' => $request->interest_type,
                'interest_rate' => $request->interest_rate,
                'tenor_months' => $request->tenor_months,
                'start_date' => $request->start_date,
                'due_day' => $request->due_day,
                'wallet_id' => $wallet?->id,
                'disburses_to_wallet' => $disbursesToWallet,
                'auto_debet' => $autoDebet,
                'auto_wallet_id' => $autoDebet ? $request->auto_wallet_id : null,
                'notes' => $request->notes,
                'evidence_path' => $evidencePath,
                'created_by' => $request->user()->id,
            ]);

            $this->debtService->generateSchedule($debt);

            if ($disbursesToWallet) {
                $this->debtService->disburse($debt);
            }

            foreach ($request->input('paid_installments', []) as $installmentNumber) {
                $installment = $debt->installments()->where('installment_number', $installmentNumber)->first();
                if ($installment) {
                    $this->debtService->markInstallmentHistorical($installment, true);
                }
            }

            return $debt;
        });

        return response()->json($debt->load(['installments', 'wallet']), 201);
    }

    public function show(Request $request, Debt $debt)
    {
        if ($debt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($debt->load([
            'installments' => fn ($q) => $q->withCount('transactions'),
            'wallet', 'autoWallet', 'transactions',
        ]));
    }

    public function destroy(Request $request, Debt $debt)
    {
        if ($debt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ((float) $debt->paid_amount > 0) {
            return response()->json([
                'message' => 'Hutang/piutang ini sudah punya riwayat pembayaran dan tidak bisa dihapus',
            ], 422);
        }

        $debt->delete();

        return response()->json(['message' => 'Hutang/piutang dihapus']);
    }

    public function payInstallment(Request $request, DebtInstallment $installment)
    {
        if ($installment->debt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'wallet_id' => 'required|exists:wallets,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $wallet = \App\Models\Wallet::where('id', $request->wallet_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $wallet) {
            return response()->json(['message' => 'Dompet tidak ditemukan'], 404);
        }

        $transaction = $this->debtService->payInstallment(
            $installment,
            (float) $request->amount,
            $wallet->id,
            $request->user(),
        );

        return response()->json([
            'transaction' => $transaction,
            'debt' => $installment->debt->fresh(['installments']),
        ]);
    }

    public function payOff(Request $request, Debt $debt)
    {
        if ($debt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'wallet_id' => 'required|exists:wallets,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $wallet = \App\Models\Wallet::where('id', $request->wallet_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $wallet) {
            return response()->json(['message' => 'Dompet tidak ditemukan'], 404);
        }

        try {
            $transaction = $this->debtService->payOff($debt, $wallet->id, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'transaction' => $transaction,
            'debt' => $debt->fresh(['installments']),
        ]);
    }

    /**
     * Tandai/batalkan status lunas satu cicilan secara historis, tanpa
     * transaksi kas - untuk hutang/piutang yang dicatat setelah cicilannya
     * berjalan di dunia nyata.
     */
    public function markInstallmentHistorical(Request $request, DebtInstallment $installment)
    {
        if ($installment->debt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'paid' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->debtService->markInstallmentHistorical($installment, $request->boolean('paid'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'debt' => $installment->debt->fresh(['installments' => fn ($q) => $q->withCount('transactions')]),
        ]);
    }
}
