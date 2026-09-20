<?php

namespace Database\Seeders;

use App\Models\AttendanceStatus;
use App\Models\Employee;
use App\Models\FoodChargeRate;
use App\Models\PayrollCompany;
use App\Models\PayrollMasterItem;
use Illuminate\Database\Seeder;

/**
 * Enough payroll data to see the module working: both companies, staff on each,
 * the food charge Hotel Pallav pays Pallav Food, and the lists behind the forms.
 *
 * Everything is matched on a natural key, so running it twice changes nothing
 * and it never touches records that are already there.
 */
class PayrollDemoSeeder extends Seeder
{
    public function run(): void
    {
        PayrollCompany::ensureFixed();

        $hotel = PayrollCompany::where('code', 'HP01')->first();
        $food = PayrollCompany::where('code', 'PF01')->first();

        $this->details($hotel, 'Ring Road, Rajkot', 'Mihir Jungiwala', 'Managing Director');
        $this->details($food, 'Ring Road, Rajkot', 'Mihir Jungiwala', 'Managing Director');

        $this->masters();

        // Hotel Pallav: everyone here eats at Pallav Food except the one who does not
        $this->staff($hotel, [
            ['EMP001', 'Rohit Sharma', 'General Manager', 'Management', 45000, '2024-06-10', true],
            ['EMP002', 'Harsh Vaghela', 'Front Office Supervisor', 'Front Office', 22000, '2024-08-26', true],
            ['EMP003', 'Sneha Patel', 'Front Office Executive', 'Front Office', 18000, '2025-07-03', true],
            ['EMP004', 'Kiran Rathod', 'Housekeeping Supervisor', 'Housekeeping', 16000, '2023-03-06', true],
            ['EMP005', 'Manoj Chauhan', 'Chef', 'Kitchen', 25000, '2025-08-23', false],
        ]);

        // Pallav Food's own staff eat there too, at the same price
        $this->staff($food, [
            ['PF001', 'Alpesh Joshi', 'Restaurant Manager', 'Management', 32000, '2024-02-12', true],
            ['PF002', 'Nilesh Bhatt', 'Head Cook', 'Kitchen', 24000, '2024-05-20', true],
            ['PF003', 'Priya Mehta', 'Steward', 'Service', 14000, '2025-01-15', false],
        ]);

        // A day on leave is not a day of meals, in both companies
        AttendanceStatus::whereIn('payroll_company_id', array_filter([$hotel?->id, $food?->id]))
            ->where(fn ($q) => $q->where('name', 'like', '%leave%')->orWhere('name', 'like', '%absent%'))
            ->update(['skips_food' => true]);

        // What Pallav Food charges per employee per month. Its price, not Hotel Pallav's
        if ($food && ! FoodChargeRate::where('payroll_company_id', $food->id)->exists()) {
            FoodChargeRate::create([
                'payroll_company_id' => $food->id,
                'monthly_amount' => 3000,
                'effective_from' => now()->subMonths(6)->startOfMonth(),
            ]);
        }
    }

    private function details(?PayrollCompany $company, string $address, string $signatory, string $designation): void
    {
        if (! $company || filled($company->address)) {
            return;
        }

        $company->update([
            'address' => $address, 'city' => 'Rajkot', 'state' => 'Gujarat', 'pincode' => '360001',
            'mobile_number' => '9825000000', 'owner_name' => $signatory,
            'authorized_person_name' => $signatory, 'authorized_designation' => $designation,
        ]);
    }

    /** The lists the forms offer, shared by both companies. */
    private function masters(): void
    {
        $lists = [
            'designations' => ['General Manager', 'Front Office Supervisor', 'Front Office Executive', 'Housekeeping Supervisor', 'Chef', 'Head Cook', 'Steward', 'Restaurant Manager'],
            'departments' => ['Management', 'Front Office', 'Housekeeping', 'Kitchen', 'Service', 'Accounts'],
            'share_emails' => [],
        ];

        foreach ($lists as $list => $labels) {
            foreach ($labels as $order => $label) {
                PayrollMasterItem::firstOrCreate(
                    ['payroll_company_id' => null, 'list' => $list, 'label' => $label],
                    ['is_active' => true, 'sort_order' => $order + 1],
                );
            }
        }

        PayrollMasterItem::firstOrCreate(
            ['payroll_company_id' => null, 'list' => 'share_emails', 'value' => 'accounts@hotelpallav.in'],
            ['label' => 'Accounts', 'is_active' => true, 'sort_order' => 1],
        );
    }

    /** @param array<int, array{0:string,1:string,2:string,3:string,4:int,5:string,6:bool}> $people */
    private function staff(?PayrollCompany $company, array $people): void
    {
        if (! $company) {
            return;
        }

        foreach ($people as [$code, $name, $designation, $department, $salary, $joined, $eats]) {
            $employee = Employee::firstOrCreate(
                ['payroll_company_id' => $company->id, 'employee_code' => $code],
                [
                    'name' => $name, 'designation' => $designation, 'department' => $department,
                    'salary' => $salary, 'joining_date' => $joined, 'daily_working_hours' => 8,
                    'payment_mode' => 'Cash', 'is_active' => true, 'eats_at_pallav_food' => $eats,
                    'contact_country' => '91', 'contact_number' => '98250'.str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT),
                ],
            );

            // Someone already on the payroll keeps everything else they have, but
            // the meals switch is what makes the food charge visible at all
            if ($eats && ! $employee->eats_at_pallav_food) {
                $employee->update(['eats_at_pallav_food' => true]);
            }
        }
    }
}
