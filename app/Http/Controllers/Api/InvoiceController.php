<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::with('transactions')
            ->where('user_id', $request->user()->id)
            ->orderBy('invoice_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($invoices);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:150',
            'invoice_date' => 'required|date',
            'notes' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:150',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        $items = collect($request->items)->map(fn ($item) => [
            'name' => $item['name'],
            'qty' => (float) $item['qty'],
            'price' => (float) $item['price'],
            'amount' => round((float) $item['qty'] * (float) $item['price'], 2),
        ])->values()->all();

        $subtotal = round(array_sum(array_column($items, 'amount')), 2);

        $invoice = DB::transaction(function () use ($request, $user, $items, $subtotal) {
            return Invoice::create([
                'user_id' => $user->id,
                'invoice_number' => $this->generateInvoiceNumber($user->id),
                'invoice_date' => $request->invoice_date,
                'customer_name' => $request->customer_name,
                'items' => $items,
                'subtotal' => $subtotal,
                'total' => $subtotal, // belum ada pajak/diskon
                'notes' => $request->notes,
                'business_name' => $user->business_name,
                'business_tagline' => $user->business_tagline,
                'business_address' => $user->business_address,
                'business_phone' => $user->business_phone,
                'business_logo_path' => $user->business_logo_path,
                'created_by' => $user->id,
            ]);
        });

        return response()->json($invoice, 201);
    }

    public function show(Request $request, Invoice $invoice)
    {
        if ($invoice->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($invoice->load(['transactions.wallet']));
    }

    public function destroy(Request $request, Invoice $invoice)
    {
        if ($invoice->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($invoice->transactions()->exists()) {
            return response()->json([
                'message' => 'Invoice ini sudah punya transaksi pemasukan terkait dan tidak bisa dihapus',
            ], 422);
        }

        $invoice->delete();

        return response()->json(['message' => 'Invoice dihapus']);
    }

    /**
     * Format: INV-YYYYMMDD-0001, urut ulang tiap hari per user. Cukup aman
     * untuk skala aplikasi ini - dibungkus lock singkat supaya dua invoice
     * yang dibuat nyaris bersamaan tidak kebagian nomor urut yang sama.
     */
    private function generateInvoiceNumber(int $userId): string
    {
        return DB::transaction(function () use ($userId) {
            $today = now()->format('Ymd');
            $prefix = "INV-{$today}-";

            $count = Invoice::where('user_id', $userId)
                ->where('invoice_number', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->count();

            return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
        });
    }
}
