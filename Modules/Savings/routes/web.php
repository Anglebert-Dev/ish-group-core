<?php

use Illuminate\Support\Facades\Route;
use Modules\Savings\Http\Controllers\AccountController;
use Modules\Savings\Http\Controllers\BudgetController;
use Modules\Savings\Http\Controllers\CategoryController;
use Modules\Savings\Http\Controllers\DataTransferController;
use Modules\Savings\Http\Controllers\ForecastController;
use Modules\Savings\Http\Controllers\GoalController;
use Modules\Savings\Http\Controllers\ObligationController;
use Modules\Savings\Http\Controllers\OverviewController;
use Modules\Savings\Http\Controllers\PlanningController;
use Modules\Savings\Http\Controllers\ReconcileController;
use Modules\Savings\Http\Controllers\ReportController;
use Modules\Savings\Http\Controllers\SettingsController;
use Modules\Savings\Http\Controllers\TransactionController;

/*
|--------------------------------------------------------------------------
| Savings: /app/savings/...
|--------------------------------------------------------------------------
*/

Route::prefix('app/savings')->name('savings.')
    ->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])
    ->group(function () {
        Route::get('/', [OverviewController::class, 'index'])->middleware('permission:list savings.overview')->name('overview');

        Route::get('/accounts', [AccountController::class, 'index'])->middleware('permission:list savings.account')->name('accounts.index');
        Route::post('/accounts', [AccountController::class, 'store'])->middleware('permission:create savings.account')->name('accounts.store');
        Route::get('/accounts/{account}', [AccountController::class, 'show'])->middleware('permission:read savings.account')->name('accounts.show');
        Route::put('/accounts/{account}', [AccountController::class, 'update'])->middleware('permission:update savings.account')->name('accounts.update');
        Route::put('/accounts/{account}/archive', [AccountController::class, 'archive'])->middleware('permission:update savings.account')->name('accounts.archive');

        Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:list savings.category')->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:create savings.category')->name('categories.store');
        Route::post('/categories/starter', [CategoryController::class, 'starter'])->middleware('permission:create savings.category')->name('categories.starter');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:update savings.category')->name('categories.update');

        Route::get('/transactions', [TransactionController::class, 'index'])->middleware('permission:list savings.transaction')->name('transactions.index');
        Route::get('/transactions/new', [TransactionController::class, 'create'])->middleware('permission:create savings.transaction')->name('transactions.create');
        Route::post('/transactions', [TransactionController::class, 'store'])->middleware('permission:create savings.transaction')->name('transactions.store');
        Route::get('/transactions/export/{format}', [TransactionController::class, 'export'])->middleware('permission:list savings.transaction')->name('transactions.export');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->middleware('permission:read savings.transaction')->name('transactions.show');
        Route::post('/transactions/{transaction}/reverse', [TransactionController::class, 'reverse'])->middleware(['permission:delete savings.transaction', 'password.confirm.staff'])->name('transactions.reverse');
        Route::post('/transactions/{transaction}/refund', [TransactionController::class, 'refund'])->middleware('permission:create savings.transaction')->name('transactions.refund');

        Route::get('/budgets', [BudgetController::class, 'index'])->middleware('permission:list savings.budget')->name('budgets.index');
        Route::put('/budgets', [BudgetController::class, 'update'])->middleware('permission:update savings.budget')->name('budgets.update');
        Route::post('/budgets/copy', [BudgetController::class, 'copy'])->middleware('permission:update savings.budget')->name('budgets.copy');

        Route::get('/planning', [PlanningController::class, 'index'])->middleware('permission:list savings.planning')->name('planning.index');
        Route::post('/planning/expected', [PlanningController::class, 'storeExpected'])->middleware('permission:create savings.planning')->name('planning.expected.store');
        Route::post('/planning/expected/{expected}/post', [PlanningController::class, 'post'])->middleware(['permission:create savings.planning', 'permission:create savings.transaction'])->name('planning.expected.post');
        Route::post('/planning/expected/{expected}/match', [PlanningController::class, 'match'])->middleware('permission:create savings.planning')->name('planning.expected.match');
        Route::post('/planning/expected/{expected}/skip', [PlanningController::class, 'skip'])->middleware('permission:update savings.planning')->name('planning.expected.skip');
        Route::put('/planning/expected/{expected}', [PlanningController::class, 'updateExpected'])->middleware('permission:update savings.planning')->name('planning.expected.update');
        Route::post('/planning/rules', [PlanningController::class, 'storeRule'])->middleware('permission:create savings.planning')->name('planning.rules.store');
        Route::put('/planning/rules/{rule}', [PlanningController::class, 'updateRule'])->middleware('permission:update savings.planning')->name('planning.rules.update');
        Route::post('/planning/commitments', [PlanningController::class, 'storeCommitment'])->middleware('permission:create savings.planning')->name('planning.commitments.store');
        Route::post('/planning/commitments/{commitment}/settle', [PlanningController::class, 'settleCommitment'])->middleware(['permission:update savings.planning', 'permission:create savings.transaction'])->name('planning.commitments.settle');
        Route::post('/planning/commitments/{commitment}/cancel', [PlanningController::class, 'cancelCommitment'])->middleware('permission:update savings.planning')->name('planning.commitments.cancel');

        Route::get('/obligations', [ObligationController::class, 'index'])->middleware('permission:list savings.obligation')->name('obligations.index');
        Route::post('/obligations', [ObligationController::class, 'store'])->middleware('permission:create savings.obligation')->name('obligations.store');
        Route::get('/obligations/{obligation}', [ObligationController::class, 'show'])->middleware('permission:read savings.obligation')->name('obligations.show');
        Route::post('/obligations/{obligation}/repay', [ObligationController::class, 'repay'])->middleware(['permission:update savings.obligation', 'permission:create savings.transaction'])->name('obligations.repay');
        Route::post('/obligations/{obligation}/basis', [ObligationController::class, 'basis'])->middleware('permission:update savings.obligation')->name('obligations.basis');
        Route::post('/obligations/{obligation}/link', [ObligationController::class, 'link'])->middleware('permission:update savings.obligation')->name('obligations.link');
        Route::post('/obligations/{obligation}/adjust', [ObligationController::class, 'adjust'])->middleware('permission:update savings.obligation')->name('obligations.adjust');
        Route::post('/obligations/{obligation}/dispute', [ObligationController::class, 'dispute'])->middleware('permission:update savings.obligation')->name('obligations.dispute');
        Route::post('/obligations/{obligation}/write-off', [ObligationController::class, 'writeOff'])->middleware(['permission:delete savings.obligation', 'password.confirm.staff'])->name('obligations.write-off');

        Route::get('/goals', [GoalController::class, 'index'])->middleware('permission:list savings.goal')->name('goals.index');
        Route::post('/goals', [GoalController::class, 'store'])->middleware('permission:create savings.goal')->name('goals.store');
        Route::put('/goals/{goal}', [GoalController::class, 'update'])->middleware('permission:update savings.goal')->name('goals.update');
        Route::post('/goals/{goal}/allocate', [GoalController::class, 'allocate'])->middleware('permission:update savings.goal')->name('goals.allocate');
        Route::post('/goals/{goal}/reassign', [GoalController::class, 'reassign'])->middleware('permission:update savings.goal')->name('goals.reassign');
        Route::post('/goals/{goal}/contribute', [GoalController::class, 'contribute'])->middleware(['permission:update savings.goal', 'permission:create savings.transaction'])->name('goals.contribute');

        Route::get('/forecast', [ForecastController::class, 'index'])->middleware('permission:list savings.overview')->name('forecast');

        Route::get('/reconcile', [ReconcileController::class, 'index'])->middleware('permission:list savings.reconciliation')->name('reconcile.index');
        Route::post('/reconcile', [ReconcileController::class, 'store'])->middleware('permission:create savings.reconciliation')->name('reconcile.store');
        Route::get('/reconcile/{reconciliation}', [ReconcileController::class, 'show'])->middleware('permission:read savings.reconciliation')->name('reconcile.show');
        Route::post('/reconcile/{reconciliation}/explain', [ReconcileController::class, 'explain'])->middleware('permission:update savings.reconciliation')->name('reconcile.explain');
        Route::post('/reconcile/{reconciliation}/adjust', [ReconcileController::class, 'adjust'])->middleware(['permission:update savings.reconciliation', 'password.confirm.staff'])->name('reconcile.adjust');

        Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:list savings.report')->name('reports');
        Route::get('/reports/export/{format}', [ReportController::class, 'export'])->middleware('permission:list savings.report')->name('reports.export');

        Route::get('/data', [DataTransferController::class, 'index'])->middleware('permission:list savings.data')->name('data.index');
        Route::post('/data/import', [DataTransferController::class, 'preview'])->middleware(['permission:create savings.data', 'permission:create savings.transaction'])->name('data.preview');
        Route::get('/data/import/{batch}', [DataTransferController::class, 'showBatch'])->middleware('permission:create savings.data')->name('data.batch');
        Route::post('/data/import/{batch}', [DataTransferController::class, 'commit'])->middleware(['permission:create savings.data', 'permission:create savings.transaction'])->name('data.commit');
        Route::get('/data/backup', [DataTransferController::class, 'backup'])->middleware(['permission:read savings.data', 'password.confirm.staff'])->name('data.backup');
        Route::post('/data/restore', [DataTransferController::class, 'restore'])->middleware(['permission:update savings.data', 'password.confirm.staff'])->name('data.restore');

        Route::get('/settings', [SettingsController::class, 'edit'])->middleware('permission:update savings.setting')->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->middleware('permission:update savings.setting')->name('settings.update');
    });
