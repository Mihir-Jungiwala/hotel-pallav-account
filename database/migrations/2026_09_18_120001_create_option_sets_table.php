<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The lists that used to be typed into the templates: payment modes, hotel
 * plans, shifts, expense heads. They live here so the SuperAdmin can change
 * them without a developer, and every form reads the same list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_sets', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('icon', 40)->default('bi-list-ul');
            $table->string('input', 20)->default('select');   // select | radio | checkbox
            $table->boolean('is_system')->default(false);     // shipped with the app, cannot be deleted
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('option_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_set_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('value');
            $table->string('color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['option_set_id', 'value']);
        });

        $sets = [
            ['payment_mode', 'Payment Modes', 'How a guest settles a bill or advance', 'bi-credit-card', 'select',
                ['Cash', 'Card', 'Debit', 'Bank Transfer', 'Cheque', 'UPI', 'Complimentary']],
            ['hotel_plan', 'Hotel Plans', 'Room packages offered on a bill', 'bi-house-check', 'select',
                ['EP', 'CP', 'MAP', 'AP']],
            ['shift', 'Shifts', 'Front desk shifts used on handovers', 'bi-clock-history', 'select',
                ['Morning', 'Afternoon', 'Evening', 'Night']],
            ['revenue_source', 'Revenue Sources', 'Where a cash deposit came from', 'bi-cash-coin', 'select',
                ['Front Desk Collection', 'Restaurant Collection', 'Banquet', 'Laundry', 'Other']],
            ['expense_head', 'Expense Heads', 'Grouping for miscellaneous expenses', 'bi-tags', 'select',
                ['Kitchen', 'Housekeeping', 'Maintenance', 'Utilities', 'Marketing', 'Transport', 'Office', 'Other']],
            ['salary_payment_mode', 'Salary Payment Modes', 'How staff salary is paid out', 'bi-wallet2', 'select',
                ['Cash', 'Bank']],
            ['advance_deduction_type', 'Advance Deduction Types', 'How a staff advance is recovered', 'bi-arrow-repeat', 'select',
                ['One Time', 'Monthly']],
            ['bonus_type', 'Bonus Types', 'Extra payments added to a salary', 'bi-gift', 'select',
                ['Bonus', 'Incentive']],
            ['attendance_status_type', 'Attendance Status Types', 'Whether a status is paid or unpaid', 'bi-calendar-check', 'select',
                ['Paid', 'Unpaid']],
            ['separation_type', 'Exit Types', 'Reason an employee leaves', 'bi-box-arrow-right', 'select',
                ['Resignation', 'Termination', 'Retirement', 'End of Contract', 'Absconded']],
            ['gender', 'Gender', 'Used on employee records', 'bi-person', 'radio',
                ['Male', 'Female', 'Other']],
        ];

        $now = now();

        foreach ($sets as $order => [$key, $name, $description, $icon, $input, $items]) {
            $setId = DB::table('option_sets')->insertGetId([
                'key' => $key, 'name' => $name, 'description' => $description, 'icon' => $icon,
                'input' => $input, 'is_system' => true, 'sort_order' => $order + 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            foreach ($items as $position => $label) {
                DB::table('option_items')->insert([
                    'option_set_id' => $setId, 'label' => $label, 'value' => $label,
                    'is_active' => true, 'is_default' => $position === 0,
                    'sort_order' => $position + 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('option_items');
        Schema::dropIfExists('option_sets');
    }
};
