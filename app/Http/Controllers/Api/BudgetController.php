<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $month = (int) ($request->input('month') ?? now()->month);
        $year = (int) ($request->input('year') ?? now()->year);
        // Rencana dari checklist (item yang belum dicentang) cuma relevan buat
        // bulan berjalan saat ini - browsing budget bulan lain (lalu/nanti)
        // tidak ada hubungannya dengan checklist yang sedang aktif sekarang.
        $isCurrentMonth = $month === (int) now()->month && $year === (int) now()->year;

        $budgets = Budget::where('user_id', $request->user()->id)
            ->where('month', $month)
            ->where('year', $year)
            ->with('category')
            ->get();

        $result = $budgets->map(function (Budget $budget) use ($request, $month, $year, $isCurrentMonth) {
            $spent = (float) Transaction::where('user_id', $request->user()->id)
                ->where('category_id', $budget->category_id)
                ->where('type', 'expense')
                ->whereYear('transaction_date', $year)
                ->whereMonth('transaction_date', $month)
                ->sum('amount');

            $planned = 0.0;
            if ($isCurrentMonth) {
                $planned = (float) DB::table('checklist_run_items')
                    ->join('checklist_runs', 'checklist_runs.id', '=', 'checklist_run_items.checklist_run_id')
                    ->join('checklists', 'checklists.id', '=', 'checklist_runs.checklist_id')
                    ->where('checklists.user_id', $request->user()->id)
                    ->where('checklist_run_items.category_id', $budget->category_id)
                    ->where('checklist_run_items.is_checked', false)
                    ->where('checklist_runs.status', 'active')
                    ->sum('checklist_run_items.target_price');
            }

            $amount = (float) $budget->amount;
            $committed = $spent + $planned;

            return [
                'id' => $budget->id,
                'category' => $budget->category,
                'month' => $budget->month,
                'year' => $budget->year,
                'amount' => $amount,
                'spent_amount' => round($spent, 2),
                'planned_amount' => round($planned, 2),
                'remaining' => round($amount - $committed, 2),
                'percentage' => $amount > 0 ? round(($committed / $amount) * 100, 1) : 0,
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
            ->where('type', 'expense')
            ->where(function ($q) use ($request) {
                $q->whereNull('user_id')->orWhere('user_id', $request->user()->id);
            })
            ->first();

        if (! $category) {
            return response()->json(['message' => 'Kategori tidak valid'], 422);
        }

        $budget = Budget::updateOrCreate(
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

        return response()->json($budget->load('category'), 201);
    }

    public function destroy(Request $request, Budget $budget)
    {
        if ($budget->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $budget->delete();

        return response()->json(['message' => 'Budget dihapus']);
    }
}
