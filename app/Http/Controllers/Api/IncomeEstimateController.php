<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\IncomeEstimate;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IncomeEstimateController extends Controller
{
    public function index(Request $request)
    {
        $month = (int) ($request->input('month') ?? now()->month);
        $year = (int) ($request->input('year') ?? now()->year);

        $estimates = IncomeEstimate::where('user_id', $request->user()->id)
            ->where('month', $month)
            ->where('year', $year)
            ->with('category')
            ->get();

        $result = $estimates->map(function (IncomeEstimate $estimate) use ($request, $month, $year) {
            $actual = (float) Transaction::where('user_id', $request->user()->id)
                ->where('category_id', $estimate->category_id)
                ->where('type', 'income')
                ->whereYear('transaction_date', $year)
                ->whereMonth('transaction_date', $month)
                ->sum('amount');

            $amount = (float) $estimate->amount;

            return [
                'id' => $estimate->id,
                'category' => $estimate->category,
                'month' => $estimate->month,
                'year' => $estimate->year,
                'amount' => $amount,
                'actual_amount' => round($actual, 2),
                'remaining' => round($amount - $actual, 2),
                'percentage' => $amount > 0 ? round(($actual / $amount) * 100, 1) : 0,
            ];
        });

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = Category::where('id', $request->category_id)
            ->where('type', 'income')
            ->where(function ($q) use ($request) {
                $q->whereNull('user_id')->orWhere('user_id', $request->user()->id);
            })
            ->first();

        if (! $category) {
            return response()->json(['message' => 'Kategori tidak valid'], 422);
        }

        $estimate = IncomeEstimate::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'category_id' => $category->id,
                'month' => $request->month,
                'year' => $request->year,
            ],
            [
                'amount' => $request->amount,
                'created_by' => $request->user()->id,
            ]
        );

        return response()->json($estimate->load('category'), 201);
    }

    public function destroy(Request $request, IncomeEstimate $incomeEstimate)
    {
        if ($incomeEstimate->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $incomeEstimate->delete();

        return response()->json(['message' => 'Estimasi pemasukan dihapus']);
    }
}
