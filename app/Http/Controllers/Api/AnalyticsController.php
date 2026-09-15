<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Rincian per kategori (buat donut chart). type: income|expense|both.
     * Periode via month+year (default bulan berjalan). category_ids[] opsional
     * buat filter kategori mana saja yang ditampilkan.
     */
    public function categoryBreakdown(Request $request)
    {
        $userId = $request->user()->id;
        $type = $request->input('type', 'expense');
        $month = (int) ($request->input('month') ?? now()->month);
        $year = (int) ($request->input('year') ?? now()->year);
        $categoryIds = $request->input('category_ids');

        $query = Transaction::where('user_id', $userId)
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->with('category');

        if ($type !== 'both') {
            $query->where('type', $type);
        }

        if (is_array($categoryIds) && count($categoryIds) > 0) {
            $query->whereIn('category_id', $categoryIds);
        }

        $transactions = $query->get();

        $grouped = $transactions->groupBy('category_id')->map(function ($items) {
            $first = $items->first();
            return [
                'category_id' => $first->category_id,
                'category_name' => $first->category->name ?? 'Lainnya',
                'type' => $first->type,
                'total' => round($items->sum('amount'), 2),
            ];
        })->values()->sortByDesc('total')->values();

        $topCategory = $grouped->first();
        $grandTotal = round($grouped->sum('total'), 2);

        return response()->json([
            'type' => $type,
            'month' => $month,
            'year' => $year,
            'grand_total' => $grandTotal,
            'top_category' => $topCategory,
            'categories' => $grouped,
        ]);
    }

    /**
     * Tren bulanan (buat bar/kurva chart) sepanjang satu tahun. Default
     * tahun berjalan.
     */
    public function monthlyTrend(Request $request)
    {
        $userId = $request->user()->id;
        $year = (int) ($request->input('year') ?? now()->year);

        $rows = Transaction::where('user_id', $userId)
            ->whereYear('transaction_date', $year)
            ->select(
                DB::raw('EXTRACT(MONTH FROM transaction_date) as month'),
                'type',
                DB::raw('SUM(amount) as total')
            )
            ->groupBy(DB::raw('EXTRACT(MONTH FROM transaction_date)'), 'type')
            ->get();

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $income = $rows->first(fn ($r) => (int) $r->month === $m && $r->type === 'income');
            $expense = $rows->first(fn ($r) => (int) $r->month === $m && $r->type === 'expense');
            $months[] = [
                'month' => $m,
                'income_total' => $income ? round((float) $income->total, 2) : 0,
                'expense_total' => $expense ? round((float) $expense->total, 2) : 0,
            ];
        }

        return response()->json(['year' => $year, 'months' => $months]);
    }

    /**
     * Tren tahunan dari tahun transaksi paling awal hingga tahun berjalan.
     */
    public function yearlyTrend(Request $request)
    {
        $userId = $request->user()->id;

        $earliest = Transaction::where('user_id', $userId)->min('transaction_date');
        $startYear = $earliest ? Carbon::parse($earliest)->year : now()->year;
        $endYear = now()->year;

        $rows = Transaction::where('user_id', $userId)
            ->select(
                DB::raw('EXTRACT(YEAR FROM transaction_date) as year'),
                'type',
                DB::raw('SUM(amount) as total')
            )
            ->groupBy(DB::raw('EXTRACT(YEAR FROM transaction_date)'), 'type')
            ->get();

        $years = [];
        for ($y = $startYear; $y <= $endYear; $y++) {
            $income = $rows->first(fn ($r) => (int) $r->year === $y && $r->type === 'income');
            $expense = $rows->first(fn ($r) => (int) $r->year === $y && $r->type === 'expense');
            $years[] = [
                'year' => $y,
                'income_total' => $income ? round((float) $income->total, 2) : 0,
                'expense_total' => $expense ? round((float) $expense->total, 2) : 0,
            ];
        }

        return response()->json(['years' => $years]);
    }

    /**
     * Top 5 kategori yang paling sering overbudget dalam N bulan terakhir
     * (default 6 bulan termasuk bulan berjalan).
     */
    public function topOverbudget(Request $request)
    {
        $userId = $request->user()->id;
        $monthsBack = (int) $request->input('months', 6);

        $periods = [];
        $cursor = now()->startOfMonth();
        for ($i = 0; $i < $monthsBack; $i++) {
            $periods[] = ['month' => $cursor->month, 'year' => $cursor->year];
            $cursor = $cursor->subMonth();
        }

        $stats = [];

        foreach ($periods as $period) {
            $budgets = Budget::where('user_id', $userId)
                ->where('month', $period['month'])
                ->where('year', $period['year'])
                ->with('category')
                ->get();

            foreach ($budgets as $budget) {
                $spent = (float) Transaction::where('user_id', $userId)
                    ->where('category_id', $budget->category_id)
                    ->where('type', 'expense')
                    ->whereYear('transaction_date', $period['year'])
                    ->whereMonth('transaction_date', $period['month'])
                    ->sum('amount');

                if ($spent <= (float) $budget->amount) {
                    continue;
                }

                $key = $budget->category_id;
                if (! isset($stats[$key])) {
                    $stats[$key] = [
                        'category_id' => $budget->category_id,
                        'category_name' => $budget->category->name ?? 'Lainnya',
                        'overbudget_count' => 0,
                        'total_over' => 0.0,
                    ];
                }
                $stats[$key]['overbudget_count'] += 1;
                $stats[$key]['total_over'] += round($spent - (float) $budget->amount, 2);
            }
        }

        $top = collect($stats)
            ->sortBy([
                ['overbudget_count', 'desc'],
                ['total_over', 'desc'],
            ])
            ->take(5)
            ->values();

        return response()->json(['months_analyzed' => $monthsBack, 'top_categories' => $top]);
    }
}
