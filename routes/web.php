<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BillMasterController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\DashboardController;
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
    Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:10,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
});

// Authenticated routes — every request re-checks the account and the role ceiling
Route::middleware(['auth', 'auth.session', 'account.usable', 'role.permissions'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // My profile (every role)
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');

    // User accounts (Admin and SuperAdmin)
    Route::middleware('admin')->prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/activity', [UserController::class, 'activity'])->name('activity');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        Route::post('/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/{user}/unlock', [UserController::class, 'unlock'])->name('unlock');
        Route::put('/{user}/password', [UserController::class, 'resetPassword'])->name('reset-password');
        Route::post('/{user}/transfer-superadmin', [UserController::class, 'transferSuperAdmin'])->name('transfer-superadmin');
    });

    // Company profiles
    Route::get('/company', [CompanyProfileController::class, 'index'])->name('company.index');
    Route::post('/company', [CompanyProfileController::class, 'store'])->name('company.store');
    Route::put('/company/{company}', [CompanyProfileController::class, 'update'])->name('company.update');
    Route::delete('/company/{company}', [CompanyProfileController::class, 'destroy'])->name('company.destroy');

    // Revenue
    Route::get('/revenue', [RevenueController::class, 'index'])->name('revenue.index');
    Route::post('/revenue/hotel', [RevenueController::class, 'storeHotel'])->name('revenue.hotel.store');
    Route::post('/revenue/food', [RevenueController::class, 'storeFood'])->name('revenue.food.store');
    Route::delete('/revenue/hotel/{deposit}', [RevenueController::class, 'destroyHotel'])->name('revenue.hotel.destroy');
    Route::delete('/revenue/food/{deposit}', [RevenueController::class, 'destroyFood'])->name('revenue.food.destroy');
    Route::get('/revenue/hotel/{deposit}/view', [RevenueController::class, 'viewHotel'])->name('revenue.hotel.view');
    Route::get('/revenue/food/{deposit}/view', [RevenueController::class, 'viewFood'])->name('revenue.food.view');

    // Expenses
    Route::get('/expense', [ExpenseController::class, 'index'])->name('expense.index');
    Route::post('/expense/hotel-withdrawal', [ExpenseController::class, 'storeHotelWithdrawal'])->name('expense.hotel-withdrawal.store');
    Route::delete('/expense/hotel-withdrawal/{withdrawal}', [ExpenseController::class, 'destroyHotelWithdrawal'])->name('expense.hotel-withdrawal.destroy');
    Route::get('/expense/hotel-withdrawal/{withdrawal}/view', [ExpenseController::class, 'viewHotelWithdrawal'])->name('expense.hotel-withdrawal.view');
    Route::post('/expense/food-withdrawal', [ExpenseController::class, 'storeFoodWithdrawal'])->name('expense.food-withdrawal.store');
    Route::delete('/expense/food-withdrawal/{withdrawal}', [ExpenseController::class, 'destroyFoodWithdrawal'])->name('expense.food-withdrawal.destroy');
    Route::get('/expense/food-withdrawal/{withdrawal}/view', [ExpenseController::class, 'viewFoodWithdrawal'])->name('expense.food-withdrawal.view');

    Route::post('/expense/hotel-misc', [ExpenseController::class, 'storeHotelMisc'])->name('expense.hotel-misc.store');
    Route::put('/expense/hotel-misc/{expense}', [ExpenseController::class, 'updateHotelMisc'])->name('expense.hotel-misc.update');
    Route::delete('/expense/hotel-misc/{expense}', [ExpenseController::class, 'destroyHotelMisc'])->name('expense.hotel-misc.destroy');
    Route::get('/expense/hotel-misc/{expense}/view', [ExpenseController::class, 'viewHotelMisc'])->name('expense.hotel-misc.view');

    Route::post('/expense/food-misc', [ExpenseController::class, 'storeFoodMisc'])->name('expense.food-misc.store');
    Route::put('/expense/food-misc/{expense}', [ExpenseController::class, 'updateFoodMisc'])->name('expense.food-misc.update');
    Route::delete('/expense/food-misc/{expense}', [ExpenseController::class, 'destroyFoodMisc'])->name('expense.food-misc.destroy');
    Route::get('/expense/food-misc/{expense}/view', [ExpenseController::class, 'viewFoodMisc'])->name('expense.food-misc.view');

    Route::post('/expense/staff-advance', [ExpenseController::class, 'storeStaffAdvance'])->name('expense.staff-advance.store');
    Route::put('/expense/staff-advance/{advance}', [ExpenseController::class, 'updateStaffAdvance'])->name('expense.staff-advance.update');
    Route::delete('/expense/staff-advance/{advance}', [ExpenseController::class, 'destroyStaffAdvance'])->name('expense.staff-advance.destroy');
    Route::get('/expense/staff-advance/{advance}/view', [ExpenseController::class, 'viewStaffAdvance'])->name('expense.staff-advance.view');

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

    /*
     * Payroll (PMS). Every module below is scoped to the Current Company
     * selected on the payroll screen.
     */
    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->name('index');

        // Company Setup
        Route::post('/companies', [PayrollCompanyController::class, 'store'])->name('company.store');
        Route::put('/companies/{company}', [PayrollCompanyController::class, 'update'])->name('company.update');
        Route::delete('/companies/{company}', [PayrollCompanyController::class, 'destroy'])->name('company.destroy');
        Route::post('/companies/{company}/toggle-active', [PayrollCompanyController::class, 'toggleActive'])->name('company.toggle-active');
        Route::get('/companies/{company}/view', [PayrollCompanyController::class, 'view'])->name('company.view');

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

        // Salary payments
        Route::put('/salary-payments/{processing}', [SalaryPaymentController::class, 'update'])->name('salary-payment.update');
        Route::post('/salary-payments/bulk', [SalaryPaymentController::class, 'bulk'])->name('salary-payment.bulk');
        Route::get('/salary-payments/download', [SalaryPaymentController::class, 'download'])->name('salary-payment.download');

        // Report downloads
        Route::get('/reports/monthly/download', [SalaryReportController::class, 'monthly'])->name('report.monthly');
        Route::get('/reports/period/download', [SalaryReportController::class, 'period'])->name('report.period');
    });
});
