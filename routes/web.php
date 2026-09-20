<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BillMasterController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\ContextController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\Payroll\AttendanceController;
use App\Http\Controllers\Payroll\AttendanceStatusController;
use App\Http\Controllers\Payroll\BonusIncentiveController;
use App\Http\Controllers\Payroll\DeductionController;
use App\Http\Controllers\Payroll\EmployeeController;
use App\Http\Controllers\Payroll\ExperienceLetterController;
use App\Http\Controllers\Payroll\JoiningLetterController;
use App\Http\Controllers\Payroll\PayrollAdvanceController;
use App\Http\Controllers\Payroll\PayrollCompanyController;
use App\Http\Controllers\Payroll\PayrollController;
use App\Http\Controllers\Payroll\SalaryPaymentController;
use App\Http\Controllers\Payroll\SalaryReportController;
use App\Http\Controllers\Payroll\SalarySlipController;
use App\Http\Controllers\Payroll\SalaryUpdateController;
use App\Http\Controllers\Payroll\SeparationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\ShiftHandoverController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/login', [AuthController::class, 'showLogin']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:30,1')->name('login.attempt');

    // Second step: the code sent by email
    Route::get('/login/code', [AuthController::class, 'showVerify'])->name('login.verify');
    Route::post('/login/code', [AuthController::class, 'verify'])->middleware('throttle:30,1')->name('login.verify.check');
    Route::post('/login/code/resend', [AuthController::class, 'resend'])->middleware('throttle:6,1')->name('login.verify.resend');

    // First sign-in: replace the emailed password before the account opens
    Route::get('/set-password', [AuthController::class, 'showFirstPassword'])->name('password.first');
    Route::post('/set-password', [AuthController::class, 'setFirstPassword'])->middleware('throttle:10,1')->name('password.first.update');

    // Forgotten password, by code rather than by link
    Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetCode'])->middleware('throttle:10,1')->name('password.email');
    Route::get('/reset-password', [AuthController::class, 'showResetCode'])->name('password.code');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
});

// Authenticated routes - every request re-checks the account and the role ceiling
Route::middleware(['auth', 'auth.session', 'single.session', 'account.usable', 'role.permissions'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Which business is in view, and the SuperAdmin override
    Route::post('/business-unit', [ContextController::class, 'switchUnit'])->name('unit.switch');
    Route::post('/force-mode', [ContextController::class, 'toggleForceMode'])->name('force-mode.toggle');

    // My profile (every role)
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');
    Route::post('/profile/two-factor', [ProfileController::class, 'toggleTwoFactor'])->name('profile.two-factor');
    Route::get('/profile/two-factor/confirm', [ProfileController::class, 'showTwoFactorConfirm'])->name('profile.two-factor.confirm');
    Route::post('/profile/two-factor/confirm', [ProfileController::class, 'confirmTwoFactor'])->middleware('throttle:20,1')->name('profile.two-factor.check');

    // Master Data: the lists every form offers (SuperAdmin only)
    Route::middleware('superadmin')->prefix('masters')->name('masters.')->group(function () {
        Route::get('/', [MasterDataController::class, 'index'])->name('index');
        Route::post('/sets', [MasterDataController::class, 'storeSet'])->name('sets.store');
        Route::put('/sets/{set}', [MasterDataController::class, 'updateSet'])->name('sets.update');
        Route::delete('/sets/{set}', [MasterDataController::class, 'destroySet'])->name('sets.destroy');
        Route::post('/sets/{set}/items', [MasterDataController::class, 'storeItem'])->name('items.store');
        Route::put('/items/{item}', [MasterDataController::class, 'updateItem'])->name('items.update');
        Route::post('/items/{item}/toggle', [MasterDataController::class, 'toggleItem'])->name('items.toggle');
        Route::post('/items/{item}/move', [MasterDataController::class, 'moveItem'])->name('items.move');
        Route::delete('/items/{item}', [MasterDataController::class, 'destroyItem'])->name('items.destroy');
    });

    // User accounts (Admin and SuperAdmin)
    Route::middleware('admin')->prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/activity', [UserController::class, 'activity'])->name('activity');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        Route::post('/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/{user}/unlock', [UserController::class, 'unlock'])->name('unlock');
        Route::post('/{user}/two-factor', [UserController::class, 'toggleTwoFactor'])->name('two-factor');
        Route::put('/{user}/password', [UserController::class, 'resetPassword'])->name('reset-password');
        Route::post('/{user}/transfer-superadmin', [UserController::class, 'transferSuperAdmin'])->name('transfer-superadmin');
        Route::get('/{user}/detail', [AccessController::class, 'user'])->name('detail');
        Route::post('/{user}/permission', [AccessController::class, 'userOverride'])->name('permission');
    });

    // The ladder of roles and what each rung may do
    Route::middleware('admin')->prefix('access')->name('access.')->group(function () {
        Route::get('/', [AccessController::class, 'index'])->name('index');
        Route::post('/roles', [AccessController::class, 'store'])->name('store');
        Route::put('/roles/{role}', [AccessController::class, 'update'])->name('update');
        Route::delete('/roles/{role}', [AccessController::class, 'destroy'])->name('destroy');
        Route::post('/roles/order', [AccessController::class, 'reorder'])->name('reorder');
        Route::post('/roles/{role}/permission', [AccessController::class, 'togglePermission'])->name('permission');
    });

    // Company profiles
    Route::get('/company', [CompanyProfileController::class, 'index'])->name('company.index');
    Route::post('/company', [CompanyProfileController::class, 'store'])->name('company.store');
    Route::put('/company/{company}', [CompanyProfileController::class, 'update'])->name('company.update');
    Route::delete('/company/{company}', [CompanyProfileController::class, 'destroy'])->name('company.destroy');
    Route::get('/company/{company}/view', [CompanyProfileController::class, 'view'])->name('company.view');

    // Revenue: one set of handlers for both cash books, named revenue.hotel.* and revenue.food.*
    Route::get('/revenue', [RevenueController::class, 'index'])->name('revenue.index');
    foreach (['hotel', 'food'] as $book) {
        Route::post("/revenue/$book", [RevenueController::class, 'store'])->defaults('book', $book)->name("revenue.$book.store");
        Route::put("/revenue/$book/{record}", [RevenueController::class, 'update'])->defaults('book', $book)->whereNumber('record')->name("revenue.$book.update");
        Route::delete("/revenue/$book/{record}", [RevenueController::class, 'destroy'])->defaults('book', $book)->whereNumber('record')->name("revenue.$book.destroy");
        Route::get("/revenue/$book/{record}/view", [RevenueController::class, 'view'])->defaults('book', $book)->whereNumber('record')->name("revenue.$book.view");
    }

    // Expenses: hotel and food withdrawals, hotel and food misc. expenses, staff advances
    Route::get('/expense', [ExpenseController::class, 'index'])->name('expense.index');
    foreach (array_keys(ExpenseController::TYPES) as $type) {
        Route::post("/expense/$type", [ExpenseController::class, 'store'])->defaults('type', $type)->name("expense.$type.store");
        Route::put("/expense/$type/{record}", [ExpenseController::class, 'update'])->defaults('type', $type)->whereNumber('record')->name("expense.$type.update");
        Route::delete("/expense/$type/{record}", [ExpenseController::class, 'destroy'])->defaults('type', $type)->whereNumber('record')->name("expense.$type.destroy");
        Route::get("/expense/$type/{record}/view", [ExpenseController::class, 'view'])->defaults('type', $type)->whereNumber('record')->name("expense.$type.view");
    }

    // Shift handover
    Route::get('/shift-handover', [ShiftHandoverController::class, 'index'])->name('shift-handover.index');
    Route::post('/shift-handover', [ShiftHandoverController::class, 'store'])->name('shift-handover.store');
    Route::put('/shift-handover/{shiftHandover}', [ShiftHandoverController::class, 'update'])->name('shift-handover.update');
    Route::delete('/shift-handover/{shiftHandover}', [ShiftHandoverController::class, 'destroy'])->name('shift-handover.destroy');
    Route::get('/shift-handover/{shiftHandover}/view', [ShiftHandoverController::class, 'view'])->name('shift-handover.view');

    // Bill Master
    Route::get('/bill-master/advances', [BillMasterController::class, 'advances'])->name('bill-master.advances');
    Route::post('/bill-master/advances', [BillMasterController::class, 'storeAdvance'])->name('bill-master.advances.store');
    Route::put('/bill-master/advances/{advance}', [BillMasterController::class, 'updateAdvance'])->name('bill-master.advances.update');
    Route::delete('/bill-master/advances/{advance}', [BillMasterController::class, 'destroyAdvance'])->name('bill-master.advances.destroy');
    Route::post('/bill-master/advances/{advance}/refund', [BillMasterController::class, 'refundAdvance'])->name('bill-master.advances.refund');
    Route::delete('/bill-master/advances/{advance}/refund', [BillMasterController::class, 'destroyRefund'])->name('bill-master.advances.refund.destroy');

    Route::get('/bill-master/bills', [BillMasterController::class, 'bills'])->name('bill-master.bills');
    Route::post('/bill-master/bills', [BillMasterController::class, 'storeBill'])->name('bill-master.bills.store');
    Route::put('/bill-master/bills/{bill}', [BillMasterController::class, 'updateBill'])->name('bill-master.bills.update');
    Route::delete('/bill-master/bills/{bill}', [BillMasterController::class, 'destroyBill'])->name('bill-master.bills.destroy');

    Route::get('/bill-master/debit-bills', [BillMasterController::class, 'debitBills'])->name('bill-master.debit-bills');
    Route::post('/bill-master/debit-bills/{bill}', [BillMasterController::class, 'storeDebitBill'])->name('bill-master.debit-bills.store');
    Route::delete('/bill-master/debit-bills/{bill}', [BillMasterController::class, 'destroyDebitBill'])->name('bill-master.debit-bills.destroy');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/download', [ReportController::class, 'download'])->name('reports.download');

    /*
     * Payroll (PMS).
     *
     * The module's home is the Company Listing: you choose a company there,
     * and that company stays active in the session while you move between the
     * individual payroll pages below. Each feature is its own page, its own
     * route and its own menu entry - they share only the selected company,
     * and "payroll.company" refuses any of them until one is chosen.
     */
    Route::prefix('payroll')->name('payroll.')->group(function () {
        // Company Listing - the landing page, and the only page without a
        // company already selected.
        Route::get('/', [PayrollController::class, 'index'])->name('index');
        Route::get('/companies', [PayrollController::class, 'index'])->name('company.index');

        // Payroll Log (SuperAdmin): everything done in payroll, with full detail
        Route::middleware('superadmin')->group(function () {
            Route::get('/log', [\App\Http\Controllers\Payroll\PayrollLogController::class, 'index'])->name('log.index');
            Route::get('/log/download', [\App\Http\Controllers\Payroll\PayrollLogController::class, 'download'])->name('log.download');
        });

        // Payroll Master (SuperAdmin): works on all companies, or on the selected one
        Route::middleware('superadmin')->prefix('master')->name('master.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Payroll\PayrollMasterController::class, 'index'])->name('index');
            Route::post('/{list}', [\App\Http\Controllers\Payroll\PayrollMasterController::class, 'store'])->name('store');
            Route::put('/items/{item}', [\App\Http\Controllers\Payroll\PayrollMasterController::class, 'update'])->name('update');
            Route::post('/items/{item}/toggle', [\App\Http\Controllers\Payroll\PayrollMasterController::class, 'toggle'])->name('toggle');
            Route::delete('/items/{item}', [\App\Http\Controllers\Payroll\PayrollMasterController::class, 'destroy'])->name('destroy');
        });

        // Company Setup
        Route::put('/companies/{company}', [PayrollCompanyController::class, 'update'])->name('company.update');
        Route::get('/companies/{company}/view', [PayrollCompanyController::class, 'view'])->name('company.view');

        /*
         * The individual payroll pages. Each one is reachable on its own URL,
         * and each one reads only the company currently selected.
         */
        Route::middleware('payroll.company')->group(function () {
            // The company's own dashboard - where opening a company arrives
            Route::get('/dashboard', [\App\Http\Controllers\Payroll\PayrollDashboardController::class, 'index'])->name('dashboard.index');

            Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
            Route::get('/attendance-statuses', [AttendanceStatusController::class, 'index'])->name('attendance-status.index');
            Route::get('/deductions', [DeductionController::class, 'index'])->name('deduction.index');
            Route::get('/joining-letter', [JoiningLetterController::class, 'index'])->name('joining-letter.index');
            Route::get('/staff', [EmployeeController::class, 'index'])->name('staff.index');
            Route::get('/advances', [PayrollAdvanceController::class, 'index'])->name('advance.index');
            Route::get('/bonus-incentives', [BonusIncentiveController::class, 'index'])->name('bonus-incentive.index');
            Route::get('/salary-update', [SalaryUpdateController::class, 'index'])->name('salary-update.index');
            Route::get('/salary-update/{employee}', [SalaryUpdateController::class, 'show'])->whereNumber('employee')->name('salary-update.show');
            Route::get('/salary-payments', [SalaryPaymentController::class, 'index'])->name('salary-payment.index');
            Route::get('/food-charges', [\App\Http\Controllers\Payroll\FoodChargeController::class, 'index'])->name('food-charge.index');
            Route::get('/experience-letter', [ExperienceLetterController::class, 'index'])->name('experience-letter.index');
            Route::get('/resignations', [SeparationController::class, 'index'])->name('separation.index');
            Route::get('/salary-slips', [SalarySlipController::class, 'index'])->name('salary-slip.index');
            Route::get('/reports/period', [SalaryReportController::class, 'index'])->name('period-report.index');
            Route::get('/reports/monthly', [SalaryReportController::class, 'monthlyIndex'])->name('monthly-report.index');
        });

        // Attendance Status Master
        Route::post('/attendance-statuses', [AttendanceStatusController::class, 'store'])->name('attendance-status.store');
        Route::put('/attendance-statuses/{status}', [AttendanceStatusController::class, 'update'])->name('attendance-status.update');
        Route::delete('/attendance-statuses/{status}', [AttendanceStatusController::class, 'destroy'])->name('attendance-status.destroy');
        Route::post('/attendance-statuses/{status}/toggle-active', [AttendanceStatusController::class, 'toggleActive'])->name('attendance-status.toggle-active');

        // Deduction Master
        Route::post('/deductions', [DeductionController::class, 'store'])->name('deduction.store');
        Route::put('/deductions/{deduction}', [DeductionController::class, 'update'])->name('deduction.update');
        Route::delete('/deductions/{deduction}', [DeductionController::class, 'destroy'])->name('deduction.destroy');
        Route::post('/deductions/{deduction}/toggle-active', [DeductionController::class, 'toggleActive'])->name('deduction.toggle-active');

        // Joining Letter
        Route::post('/joining-letter', [JoiningLetterController::class, 'save'])->name('joining-letter.save');
        Route::delete('/joining-letter/{joiningLetter}', [JoiningLetterController::class, 'destroy'])->name('joining-letter.destroy');
        Route::post('/joining-letter/{joiningLetter}/toggle-active', [JoiningLetterController::class, 'toggleActive'])->name('joining-letter.toggle-active');
        Route::get('/joining-letter/generate/{employee}', [JoiningLetterController::class, 'generate'])->name('joining-letter.generate');

        // Staff Management
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employee.store');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employee.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employee.destroy');
        Route::post('/employees/{employee}/toggle-active', [EmployeeController::class, 'toggleActive'])->name('employee.toggle-active');
        Route::post('/employees/{employee}/share', [EmployeeController::class, 'share'])->name('employee.share');
        Route::post('/employees/{employee}/offer-letter', [EmployeeController::class, 'sendOfferLetter'])->name('employee.offer-letter.send');
        Route::get('/employees/{employee}/view', [EmployeeController::class, 'view'])->name('employee.view');

        // Attendance Management (Generate / Re-Generate Salary live here)
        Route::post('/attendance/{month}/save', [AttendanceController::class, 'save'])->name('attendance.save');
        Route::post('/attendance/{month}/generate', [AttendanceController::class, 'generateSalary'])->name('attendance.generate');
        Route::post('/attendance/{month}/regenerate', [AttendanceController::class, 'regenerateSalary'])->name('attendance.regenerate');
        Route::post('/attendance/{month}/unlock', [AttendanceController::class, 'unlock'])->name('attendance.unlock');

        // Advance Management
        Route::post('/advances', [PayrollAdvanceController::class, 'store'])->name('advance.store');
        Route::put('/advances/{advance}', [PayrollAdvanceController::class, 'update'])->name('advance.update');
        Route::delete('/advances/{advance}', [PayrollAdvanceController::class, 'destroy'])->name('advance.destroy');
        Route::get('/advances/{advance}/view', [PayrollAdvanceController::class, 'view'])->name('advance.view');

        // Bonus & Incentive Management
        Route::post('/bonus-incentives', [BonusIncentiveController::class, 'store'])->name('bonus-incentive.store');
        Route::put('/bonus-incentives/{entry}', [BonusIncentiveController::class, 'update'])->name('bonus-incentive.update');
        Route::delete('/bonus-incentives/{entry}', [BonusIncentiveController::class, 'destroy'])->name('bonus-incentive.destroy');
        Route::get('/bonus-incentives/{entry}/view', [BonusIncentiveController::class, 'view'])->name('bonus-incentive.view');

        // Salary Update Management
        Route::put('/salary-update/{employee}', [SalaryUpdateController::class, 'update'])->name('salary-update.update');
        Route::get('/salary-update/{employee}/history', [SalaryUpdateController::class, 'history'])->name('salary-update.history');

        // Employee Salary Slip
        Route::get('/salary-slip/{processing}/view', [SalarySlipController::class, 'view'])->name('salary-slip.view');

        // Experience Letter template
        Route::post('/experience-letter', [ExperienceLetterController::class, 'save'])->name('experience-letter.save');
        Route::delete('/experience-letter/{experienceLetter}', [ExperienceLetterController::class, 'destroy'])->name('experience-letter.destroy');
        Route::post('/experience-letter/{experienceLetter}/toggle-active', [ExperienceLetterController::class, 'toggleActive'])->name('experience-letter.toggle-active');

        // Resignations & exits
        Route::post('/separations', [SeparationController::class, 'store'])->name('separation.store');
        Route::put('/separations/{separation}', [SeparationController::class, 'update'])->name('separation.update');
        Route::delete('/separations/{separation}', [SeparationController::class, 'destroy'])->name('separation.destroy');
        Route::post('/separations/{separation}/rejoin', [SeparationController::class, 'rejoin'])->name('separation.rejoin');
        Route::get('/separations/{separation}/view', [SeparationController::class, 'view'])->name('separation.view');
        Route::get('/separations/{separation}/experience-letter', [SeparationController::class, 'experienceLetter'])->name('separation.experience-letter');

        // Food charges paid to Pallav Food
        Route::post('/food-charges/rate', [\App\Http\Controllers\Payroll\FoodChargeController::class, 'saveRate'])->name('food-charge.rate');
        Route::delete('/food-charges/rate/{rate}', [\App\Http\Controllers\Payroll\FoodChargeController::class, 'destroyRate'])->name('food-charge.rate.destroy');
        Route::post('/food-charges/{employee}/toggle', [\App\Http\Controllers\Payroll\FoodChargeController::class, 'toggleEmployee'])->name('food-charge.toggle');

        // Salary payments
        Route::put('/salary-payments/{processing}', [SalaryPaymentController::class, 'update'])->name('salary-payment.update');
        Route::post('/salary-payments/bulk', [SalaryPaymentController::class, 'bulk'])->name('salary-payment.bulk');
        Route::get('/salary-payments/download', [SalaryPaymentController::class, 'download'])->name('salary-payment.download');

        // Report downloads
        Route::get('/reports/monthly/download', [SalaryReportController::class, 'monthly'])->name('report.monthly');
        Route::get('/reports/monthly/food-charges', [SalaryReportController::class, 'foodCharges'])->name('report.food-charges');
        Route::get('/reports/period/download', [SalaryReportController::class, 'period'])->name('report.period');
    });
});
