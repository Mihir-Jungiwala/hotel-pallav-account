<?php

namespace Database\Seeders;

use App\Models\AttendanceEntry;
use App\Models\AttendanceMonth;
use App\Models\AttendanceStatus;
use App\Models\BillMasterAdvance;
use App\Models\BillMasterBill;
use App\Models\BonusIncentive;
use App\Models\CompanyProfile;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\FoodCashDeposit;
use App\Models\FoodCashWithdrawal;
use App\Models\FoodMiscExpense;
use App\Models\HotelCashDeposit;
use App\Models\HotelCashWithdrawal;
use App\Models\HotelMiscExpense;
use App\Models\IdProofType;
use App\Models\JoiningLetter;
use App\Models\PayrollAdvance;
use App\Models\PayrollCompany;
use App\Models\ShiftHandover;
use App\Models\StaffAdvance;
use App\Models\User;
use App\Services\Payroll\SalaryProcessor;
use App\Support\NumberToWords;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class MockDataSeeder extends Seeder
{
    private User $admin;
    private User $editor;

    public function run(): void
    {
        $this->admin = User::firstWhere('username', 'superadmin');

        $this->editor = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Priya Shah', 'email' => 'admin@hotelpallav.com', 'password' => Hash::make('Admin@1234'),
                'role' => 'Admin', 'is_active' => true, 'created_by' => $this->admin->id, 'password_changed_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['username' => 'frontdesk'],
            [
                'name' => 'Rahul Mehta', 'email' => 'frontdesk@hotelpallav.com', 'password' => Hash::make('Editor@1234'),
                'role' => 'Editor', 'is_active' => true, 'created_by' => $this->editor->id, 'password_changed_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['username' => 'accounts'],
            [
                'name' => 'Neha Desai', 'email' => null, 'password' => Hash::make('Viewer@1234'),
                'role' => 'Viewer', 'is_active' => true, 'created_by' => $this->editor->id, 'password_changed_at' => now(),
            ]
        );

        $this->seedCompanyProfiles();
        $this->seedRevenueAndExpenses();
        $this->seedShiftHandovers();
        $this->seedBillMaster();
        $this->seedPayroll();
    }

    private function seedCompanyProfiles(): void
    {
        $companies = [
            ['name' => 'Adani Enterprises Ltd', 'gst' => '24AAACA1234B1Z5'],
            ['name' => 'Reliance Industries Ltd', 'gst' => '24AAACR5678C1Z2'],
            ['name' => 'Tata Consultancy Services', 'gst' => '24AAACT9012D1Z8'],
        ];

        foreach ($companies as $c) {
            CompanyProfile::updateOrCreate(
                ['name' => $c['name']],
                [
                    'address' => 'Corporate House, SG Highway',
                    'country' => 'India',
                    'nationality' => 'Indian',
                    'email' => strtolower(str_replace(' ', '', explode(' ', $c['name'])[0])).'@example.com',
                    'pincode' => '380015',
                    'mobile_number' => '9898989'.rand(100, 999),
                    'discount_percentage' => 10,
                    'gst_percentage' => 12,
                    'tcs_percentage' => 0,
                    'tds_percentage' => 0,
                    'gst_number' => $c['gst'],
                    'md_one_name' => 'Managing Director',
                    'md_one_mobile' => '9876543210',
                    'created_by' => $this->admin->id,
                ]
            );
        }
    }

    private function seedRevenueAndExpenses(): void
    {
        $today = Carbon::today();

        for ($i = 0; $i < 6; $i++) {
            $date = $today->copy()->subDays($i);

            $hotelAmount = rand(8000, 25000);
            HotelCashDeposit::create([
                'date' => $date, 'time' => '11:00:00', 'user_id' => $this->admin->id,
                'full_name' => $this->admin->name, 'depositor' => 'Front Desk Collection',
                'amount' => $hotelAmount, 'amount_in_words' => NumberToWords::convert($hotelAmount),
            ]);

            $foodAmount = rand(3000, 9000);
            FoodCashDeposit::create([
                'date' => $date, 'time' => '20:30:00', 'user_id' => $this->editor->id,
                'full_name' => $this->editor->name, 'depositor' => 'Restaurant Collection',
                'amount' => $foodAmount, 'amount_in_words' => NumberToWords::convert($foodAmount),
            ]);

            if ($i % 2 === 0) {
                $withdrawAmount = rand(1000, 4000);
                HotelCashWithdrawal::create([
                    'date' => $date, 'time' => '19:00:00', 'user_id' => $this->admin->id,
                    'full_name' => $this->admin->name, 'withdrawer' => 'Bank Deposit Run',
                    'amount' => $withdrawAmount, 'amount_in_words' => NumberToWords::convert($withdrawAmount),
                ]);
            }
        }

        HotelMiscExpense::create([
            'date' => $today->copy()->subDay(), 'time' => '15:00:00', 'user_id' => $this->admin->id,
            'full_name' => $this->admin->name, 'expense_name' => 'Plumbing Repair',
            'amount' => 2200, 'instruction' => 'Room 204 bathroom leak fix',
            'amount_in_words' => NumberToWords::convert(2200),
        ]);

        FoodMiscExpense::create([
            'date' => $today->copy()->subDays(2), 'time' => '10:00:00', 'user_id' => $this->editor->id,
            'full_name' => $this->editor->name, 'expense_name' => 'Vegetable Supplier Payment',
            'amount' => 5400, 'instruction' => 'Weekly fresh produce order',
            'amount_in_words' => NumberToWords::convert(5400),
        ]);

        FoodCashWithdrawal::create([
            'date' => $today->copy()->subDays(3), 'time' => '18:00:00', 'user_id' => $this->admin->id,
            'full_name' => $this->admin->name, 'withdrawer' => 'Kitchen Petty Cash',
            'amount' => 1500, 'amount_in_words' => NumberToWords::convert(1500),
        ]);
    }

    private function seedShiftHandovers(): void
    {
        $shifts = [
            ['shift' => 'Morning', 'd500' => 20, 'd200' => 15, 'd100' => 30, 'd50' => 10, 'd20' => 5, 'd10' => 8, 'd5' => 4, 'coins' => 25],
            ['shift' => 'Evening', 'd500' => 12, 'd200' => 10, 'd100' => 18, 'd50' => 6, 'd20' => 3, 'd10' => 5, 'd5' => 2, 'coins' => 15],
        ];

        foreach ($shifts as $i => $s) {
            $total = $s['d500'] * 500 + $s['d200'] * 200 + $s['d100'] * 100 + $s['d50'] * 50
                + $s['d20'] * 20 + $s['d10'] * 10 + $s['d5'] * 5 + $s['coins'] * 1;

            ShiftHandover::create([
                'date' => Carbon::today(), 'time' => $i === 0 ? '14:00:00' : '22:00:00',
                'user_id' => $this->admin->id, 'full_name' => $this->admin->name, 'shift' => $s['shift'],
                'message_one' => 'All rooms cleaned and inspected.',
                'message_two' => 'No pending guest complaints.',
                'special_instruction' => 'VIP guest arriving tomorrow in Room 301.',
                'd500_total' => $s['d500'] * 500, 'd500_count' => $s['d500'],
                'd200_total' => $s['d200'] * 200, 'd200_count' => $s['d200'],
                'd100_total' => $s['d100'] * 100, 'd100_count' => $s['d100'],
                'd50_total' => $s['d50'] * 50, 'd50_count' => $s['d50'],
                'd20_total' => $s['d20'] * 20, 'd20_count' => $s['d20'],
                'd10_total' => $s['d10'] * 10, 'd10_count' => $s['d10'],
                'd5_total' => $s['d5'] * 5, 'd5_count' => $s['d5'],
                'coins_total' => $s['coins'] * 1, 'coins_count' => $s['coins'],
                'total' => $total, 'total_in_words' => NumberToWords::convert($total),
            ]);
        }
    }

    private function seedBillMaster(): void
    {
        $company = CompanyProfile::first();
        $year = date('Y');

        $advance = BillMasterAdvance::updateOrCreate(
            ['receipt_number' => "{$year} / 1001"],
            [
                'guest_name' => 'Amit Patel', 'mobile_number' => '9825012345',
                'company_id' => $company->id, 'payment_date' => Carbon::today()->subDays(5),
                'hotel_amount' => 8000, 'food_amount' => 2000,
                'hotel_mode' => 'Cash', 'food_mode' => 'Cash',
                'hotel_balance' => 8000, 'food_balance' => 2000, 'total' => 10000,
                'created_by' => $this->admin->id,
            ]
        );

        $bill = BillMasterBill::updateOrCreate(
            ['bill_number' => "{$year} / 2001"],
            [
                'advance_id' => $advance->id, 'advance_receipt_number' => $advance->receipt_number,
                'advance_guest_name' => $advance->guest_name,
                'company_id' => $company->id, 'bill_date' => Carbon::today()->subDays(2),
                'guest_name' => 'Amit Patel', 'mobile_number' => '9825012345',
                'hotel_plan' => 'CP Plan', 'hotel_amount' => 6000, 'hotel_plan_amount' => 1000,
                'hotel_laundry_amount' => 200, 'hotel_gst' => 720, 'hotel_mode_of_payment' => 'Cash',
                'food_amount' => 1500, 'food_gst' => 180, 'food_mode_of_payment' => 'Cash',
                'total_hotel_amount' => 7920, 'total_food_amount' => 1680,
                'advance_hotel_amount' => 7920, 'advance_food_amount' => 1680,
                'advance_delete_hotel_amount' => 7920, 'advance_delete_food_amount' => 1680,
                'created_by' => $this->admin->id,
            ]
        );

        $advance->update(['hotel_balance' => 80, 'food_balance' => 320]);

        BillMasterAdvance::updateOrCreate(
            ['receipt_number' => "{$year} / 1002"],
            [
                'guest_name' => 'Sneha Joshi', 'mobile_number' => '9909087654',
                'company_id' => $company->id, 'payment_date' => Carbon::today()->subDay(),
                'hotel_amount' => 5000, 'food_amount' => 0,
                'hotel_mode' => 'Cash', 'food_mode' => null,
                'hotel_balance' => 5000, 'food_balance' => 0, 'total' => 5000,
                'created_by' => $this->editor->id,
            ]
        );

        BillMasterBill::updateOrCreate(
            ['bill_number' => "{$year} / 2002"],
            [
                'company_id' => $company->id, 'bill_date' => Carbon::today(),
                'guest_name' => 'Karan Desai', 'mobile_number' => '9998887776',
                'hotel_plan' => 'MAP Plan', 'hotel_amount' => 9000, 'hotel_gst' => 1080,
                'hotel_mode_of_payment' => 'Debit',
                'food_amount' => 2500, 'food_gst' => 300, 'food_mode_of_payment' => 'Debit',
                'total_hotel_amount' => 10080, 'total_food_amount' => 2800,
                'balance_hotel_amount' => 10080, 'balance_food_amount' => 2800,
                'created_by' => $this->admin->id,
            ]
        );
    }

    private function seedPayroll(): void
    {
        $company = PayrollCompany::updateOrCreate(
            ['code' => 'HP01'],
            [
                'name' => 'Hotel Pallav', 'owner_name' => 'Mihir Jungiwala',
                'mobile_number' => '9825000000', 'email' => 'accounts@hotelpallav.com',
                'address' => 'Ring Road', 'city' => 'Rajkot', 'state' => 'Gujarat', 'pincode' => '360001',
                'pan_number' => 'AAAPH1234M', 'tan_number' => 'RJKH12345A',
                'pf_registration_number' => 'GJ/RJK/1234567', 'esic_registration_number' => '12345678900001234',
                'professional_tax_registration_number' => 'PT12345',
                'authorized_person_name' => 'Mihir Jungiwala', 'authorized_designation' => 'Managing Director',
                'authorized_mobile' => '9825000000', 'authorized_email' => 'mihir@hotelpallav.com',
                'bank_name' => 'HDFC Bank', 'account_number' => '50100123456789',
                'ifsc_code' => 'HDFC0001234', 'branch_name' => 'Ring Road, Rajkot',
                'is_active' => true, 'created_by' => $this->admin->id,
            ]
        );

        $statuses = [
            ['name' => 'Present', 'shortcut_key' => 'P', 'color' => '#16a34a', 'attendance_percentage' => 100, 'status_type' => 'Paid'],
            ['name' => 'Three Quarter Day', 'shortcut_key' => 'TQD', 'color' => '#f97316', 'attendance_percentage' => 75, 'status_type' => 'Paid'],
            ['name' => 'Half Day', 'shortcut_key' => 'HD', 'color' => '#3b82f6', 'attendance_percentage' => 50, 'status_type' => 'Paid'],
            ['name' => 'Quarter Day', 'shortcut_key' => 'QD', 'color' => '#eab308', 'attendance_percentage' => 25, 'status_type' => 'Paid'],
            ['name' => 'Absent', 'shortcut_key' => 'A', 'color' => '#dc2626', 'attendance_percentage' => 0, 'status_type' => 'Unpaid'],
            ['name' => 'Week Off', 'shortcut_key' => 'WO', 'color' => '#8B5CF6', 'attendance_percentage' => 100, 'status_type' => 'Paid'],
            ['name' => 'Holiday', 'shortcut_key' => 'H', 'color' => '#0ea5e9', 'attendance_percentage' => 100, 'status_type' => 'Paid'],
            ['name' => 'Paid Leave', 'shortcut_key' => 'PL', 'color' => '#14b8a6', 'attendance_percentage' => 100, 'status_type' => 'Paid'],
            ['name' => 'Sick Leave', 'shortcut_key' => 'SL', 'color' => '#a855f7', 'attendance_percentage' => 100, 'status_type' => 'Paid'],
            ['name' => 'Leave Without Pay', 'shortcut_key' => 'LWP', 'color' => '#64748b', 'attendance_percentage' => 0, 'status_type' => 'Unpaid'],
        ];

        $statusModels = [];
        foreach ($statuses as $s) {
            $statusModels[$s['shortcut_key']] = AttendanceStatus::updateOrCreate(
                ['payroll_company_id' => $company->id, 'shortcut_key' => $s['shortcut_key']],
                array_merge($s, ['payroll_company_id' => $company->id, 'is_active' => true])
            );
        }

        $deductions = [
            'Food' => 750, 'Uniform' => 300, 'Accommodation' => 1500, 'Staff Loan' => 0,
        ];
        $deductionModels = [];
        foreach ($deductions as $name => $amount) {
            $deductionModels[$name] = Deduction::updateOrCreate(
                ['payroll_company_id' => $company->id, 'name' => $name],
                ['amount' => $amount, 'is_active' => true]
            );
        }

        JoiningLetter::updateOrCreate(
            ['payroll_company_id' => $company->id],
            [
                'subject' => 'Appointment Letter for {DESIGNATION}',
                'introduction_content' => 'Dear {EMPLOYEE_NAME}, we are pleased to offer you the position of {DESIGNATION} in the {DEPARTMENT} department at Hotel Pallav, effective from {JOINING_DATE}.',
                'roles_responsibilities' => '{RESPONSIBILITIES}',
                'terms_conditions' => 'Your employment is subject to the standard policies of Hotel Pallav, including working hours, leave entitlement, and code of conduct as communicated by HR.',
                'closing_message' => 'We look forward to a long and successful association with you.',
                'use_company_signatory' => true,
                'authorized_closing_text' => 'For Hotel Pallav',
                'acceptance_heading' => 'Acceptance of Offer',
                'acceptance_content' => 'I, {EMPLOYEE_NAME}, have read and understood the terms of this appointment letter and hereby accept the offer as {DESIGNATION}, effective {JOINING_DATE}.',
                'acceptance_closing_text' => 'Signed on {CURRENT_DATE}',
                'is_active' => true,
            ]
        );

        $idProof = IdProofType::first();

        $employees = [
            ['code' => 'EMP001', 'name' => 'Rohit Sharma', 'designation' => 'General Manager', 'department' => 'Management', 'salary' => 45000, 'hours' => 9, 'mode' => 'Bank'],
            ['code' => 'EMP002', 'name' => 'Harsh Vaghela', 'designation' => 'Front Office Supervisor', 'department' => 'Front Office', 'salary' => 22000, 'hours' => 8, 'mode' => 'Bank'],
            ['code' => 'EMP003', 'name' => 'Sneha Patel', 'designation' => 'Front Office Executive', 'department' => 'Front Office', 'salary' => 18000, 'hours' => 8, 'mode' => 'Cash'],
            ['code' => 'EMP004', 'name' => 'Kiran Rathod', 'designation' => 'Housekeeping Supervisor', 'department' => 'Housekeeping', 'salary' => 16000, 'hours' => 8, 'mode' => 'Cash'],
            ['code' => 'EMP005', 'name' => 'Manoj Chauhan', 'designation' => 'Chef', 'department' => 'Kitchen', 'salary' => 25000, 'hours' => 9, 'mode' => 'Bank'],
        ];

        $employeeModels = [];
        foreach ($employees as $e) {
            $employeeModels[$e['code']] = Employee::updateOrCreate(
                ['payroll_company_id' => $company->id, 'employee_code' => $e['code']],
                [
                    'name' => $e['name'], 'designation' => $e['designation'], 'department' => $e['department'],
                    'responsibilities' => "Responsible for day-to-day {$e['department']} operations and guest satisfaction.",
                    'joining_date' => Carbon::today()->subYears(rand(1, 3))->subDays(rand(0, 300)),
                    'salary' => $e['salary'], 'daily_working_hours' => $e['hours'],
                    'contact_number' => '98'.rand(10000000, 99999999),
                    'address' => 'Rajkot, Gujarat', 'payment_mode' => $e['mode'],
                    'bank_name' => $e['mode'] === 'Bank' ? 'State Bank of India' : null,
                    'account_holder_name' => $e['mode'] === 'Bank' ? $e['name'] : null,
                    'account_number' => $e['mode'] === 'Bank' ? (string) rand(100000000000, 999999999999) : null,
                    'ifsc_code' => $e['mode'] === 'Bank' ? 'SBIN0001234' : null,
                    'branch_name' => $e['mode'] === 'Bank' ? 'Rajkot Main' : null,
                    'id_proof_type_id' => $idProof?->id,
                    'id_proof_number' => strtoupper(substr(md5($e['code']), 0, 10)),
                    'is_active' => true, 'created_by' => $this->admin->id,
                ]
            );
        }

        EmployeeDeduction::updateOrCreate(
            ['employee_id' => $employeeModels['EMP003']->id, 'deduction_id' => $deductionModels['Food']->id],
            ['deduction_type' => 'Monthly', 'amount' => 750]
        );
        EmployeeDeduction::updateOrCreate(
            ['employee_id' => $employeeModels['EMP004']->id, 'deduction_id' => $deductionModels['Uniform']->id],
            ['deduction_type' => 'One Time', 'amount' => 300]
        );

        // A prior month, fully attended and processed, so Salary Slip / Reports have data
        $lastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $attendanceMonth = AttendanceMonth::firstOrCreate([
            'payroll_company_id' => $company->id,
            'year' => $lastMonth->year,
            'month' => $lastMonth->month,
        ]);

        if (! $attendanceMonth->is_locked) {
            $daysInMonth = $lastMonth->daysInMonth;

            foreach ($employeeModels as $employee) {
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $dow = $lastMonth->copy()->day($day)->dayOfWeek;
                    $key = $dow === Carbon::SUNDAY ? 'WO' : 'P';

                    // Sprinkle a little variety
                    if ($key === 'P' && $day % 11 === 0) {
                        $key = 'HD';
                    }

                    $status = $statusModels[$key];

                    AttendanceEntry::updateOrCreate(
                        ['attendance_month_id' => $attendanceMonth->id, 'employee_id' => $employee->id, 'day' => $day],
                        [
                            'attendance_status_id' => $status->id,
                            'shortcut_key' => $status->shortcut_key,
                            'attendance_percentage' => $status->attendance_percentage,
                            'overtime_hours' => $day % 7 === 0 ? 2 : 0,
                        ]
                    );
                }
            }

            // Advance and bonus dated within the processed month
            PayrollAdvance::updateOrCreate(
                ['payroll_company_id' => $company->id, 'employee_id' => $employeeModels['EMP002']->id, 'is_carry_forward' => false],
                [
                    'advance_date' => $lastMonth->copy()->addDays(4),
                    'amount' => 3000, 'deduction_type' => 'Monthly', 'deduction_amount' => 1000,
                    'remarks' => 'Personal emergency advance', 'created_by' => $this->admin->id,
                ]
            );

            BonusIncentive::updateOrCreate(
                ['payroll_company_id' => $company->id, 'employee_id' => $employeeModels['EMP005']->id, 'type' => 'Bonus'],
                [
                    'entry_date' => $lastMonth->copy()->addDays(10), 'amount' => 1500,
                    'remarks' => 'Festival bonus', 'created_by' => $this->admin->id,
                ]
            );

            BonusIncentive::updateOrCreate(
                ['payroll_company_id' => $company->id, 'employee_id' => $employeeModels['EMP001']->id, 'type' => 'Incentive'],
                [
                    'entry_date' => $lastMonth->copy()->addDays(15), 'amount' => 2000,
                    'remarks' => 'Occupancy target achieved', 'created_by' => $this->admin->id,
                ]
            );

            app(SalaryProcessor::class)->generate($attendanceMonth, $this->admin->id);
        }

        \App\Models\ExperienceLetter::updateOrCreate(
            ['payroll_company_id' => $company->id],
            [
                'subject' => 'Experience Certificate',
                'body_content' => 'This is to certify that {EMPLOYEE_NAME} was employed with us as {DESIGNATION} in the {DEPARTMENT} department from {JOINING_DATE} to {LAST_WORKING_DATE}, a period of {DURATION}.',
                'conduct_remarks' => 'During the tenure, we found {EMPLOYEE_NAME} to be sincere, hardworking and professional in conduct.',
                'closing_message' => 'We wish {EMPLOYEE_NAME} every success in future endeavours.',
                'use_company_signatory' => true,
                'authorized_closing_text' => 'For Hotel Pallav',
                'is_active' => true,
            ]
        );

        // A former employee, so Resignations and Rejoin have something to show.
        // Created after salary generation so they never enter a payroll run.
        $former = Employee::updateOrCreate(
            ['payroll_company_id' => $company->id, 'employee_code' => 'EMP006'],
            [
                'name' => 'Pooja Trivedi', 'designation' => 'Guest Relations Executive', 'department' => 'Front Office',
                'responsibilities' => 'Handled guest check-ins, concierge requests and feedback.',
                'joining_date' => Carbon::today()->subYears(2)->subMonths(3),
                'salary' => 17000, 'daily_working_hours' => 8,
                'contact_number' => '9876501234', 'address' => 'Rajkot, Gujarat', 'payment_mode' => 'Cash',
                'id_proof_type_id' => $idProof?->id, 'id_proof_number' => 'EMP006PROOF',
                'is_active' => false, 'created_by' => $this->admin->id,
            ]
        );

        \App\Models\EmployeeSeparation::updateOrCreate(
            ['employee_id' => $former->id],
            [
                'payroll_company_id' => $company->id,
                'separation_type' => 'Resignation',
                'resignation_date' => Carbon::today()->subMonths(2)->startOfMonth(),
                'last_working_date' => Carbon::today()->subMonth()->startOfMonth()->subDay(),
                'reason' => 'Pursuing higher studies in hospitality management.',
                'remarks' => 'Handover completed. Dues settled. Eligible for rehire.',
                'status' => 'Relieved',
                'created_by' => $this->admin->id,
            ]
        );

        // A one-off advance in the current (unprocessed) month too
        StaffAdvance::updateOrCreate(
            ['employee_id' => $employeeModels['EMP003']->id, 'year_month' => Carbon::now()->format('Y-m')],
            [
                'date' => Carbon::today(), 'time' => '12:00:00', 'user_id' => $this->admin->id,
                'full_name' => $this->admin->name, 'amount' => 1000,
                'instruction' => 'Advance against this month salary',
                'amount_in_words' => NumberToWords::convert(1000),
            ]
        );
    }
}
