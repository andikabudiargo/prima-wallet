<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\IncomeEstimateController;
use App\Http\Controllers\Api\ChecklistController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\RecurringTransactionController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\TargetController;
use App\Http\Controllers\Api\AnalyticsController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'googleLogin']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('wallets', WalletController::class);
    Route::apiResource('transactions', TransactionController::class)->except(['update']);
    Route::apiResource('categories', CategoryController::class)->only(['index', 'store', 'destroy']);

    Route::apiResource('debts', DebtController::class)->except(['update']);
    Route::post('/debts/{debt}/pay-off', [DebtController::class, 'payOff']);
    Route::post('/debt-installments/{installment}/pay', [DebtController::class, 'payInstallment']);
    Route::patch('/debt-installments/{installment}/historical', [DebtController::class, 'markInstallmentHistorical']);

    Route::apiResource('budgets', BudgetController::class)->only(['index', 'store', 'destroy']);
    Route::apiResource('income-estimates', IncomeEstimateController::class)->only(['index', 'store', 'destroy']);

    Route::apiResource('checklists', ChecklistController::class)->except(['update']);
    Route::put('/checklists/{checklist}', [ChecklistController::class, 'update']);
    Route::post('/checklists/{checklist}/items', [ChecklistController::class, 'addItem']);
    Route::put('/checklist-items/{item}', [ChecklistController::class, 'updateItem']);
    Route::delete('/checklist-items/{item}', [ChecklistController::class, 'deleteItem']);
    Route::post('/checklists/{checklist}/runs', [ChecklistController::class, 'startRun']);
    Route::get('/checklists/{checklist}/runs', [ChecklistController::class, 'runHistory']);
    Route::post('/checklist-run-items/{runItem}/check', [ChecklistController::class, 'checkRunItem']);
    Route::post('/checklist-run-items/{runItem}/uncheck', [ChecklistController::class, 'uncheckRunItem']);

    Route::apiResource('transfers', TransferController::class)->only(['index', 'store', 'destroy']);

    Route::apiResource('recurring-transactions', RecurringTransactionController::class)->except(['show']);

    Route::apiResource('assets', AssetController::class)->only(['index', 'store', 'destroy']);

    Route::apiResource('targets', TargetController::class)->except(['show']);
    Route::post('/targets/{target}/contributions', [TargetController::class, 'addContribution']);
    Route::delete('/target-contributions/{contribution}', [TargetController::class, 'deleteContribution']);

    Route::get('/analytics/category-breakdown', [AnalyticsController::class, 'categoryBreakdown']);
    Route::get('/analytics/monthly-trend', [AnalyticsController::class, 'monthlyTrend']);
    Route::get('/analytics/yearly-trend', [AnalyticsController::class, 'yearlyTrend']);
    Route::get('/analytics/top-overbudget', [AnalyticsController::class, 'topOverbudget']);
});