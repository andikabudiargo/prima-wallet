<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\IncomeEstimateController;
use App\Http\Controllers\Api\ChecklistController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'googleLogin']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('wallets', WalletController::class);
    Route::apiResource('transactions', TransactionController::class)->except(['update']);
    Route::apiResource('categories', CategoryController::class)->only(['index', 'store', 'destroy']);

    Route::apiResource('debts', DebtController::class)->except(['update']);
    Route::post('/debts/{debt}/pay-off', [DebtController::class, 'payOff']);
    Route::post('/debt-installments/{installment}/pay', [DebtController::class, 'payInstallment']);

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
});