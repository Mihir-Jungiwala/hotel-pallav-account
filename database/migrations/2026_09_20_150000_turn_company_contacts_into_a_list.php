<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A company's people were seven fixed slots (MD 1, MD 2, HR Head...). They are
 * now a list, so any role can be used as many times as needed and the roles
 * come from Master Data. The old slots are carried over into the list; their
 * columns stay in the table, unused.
 */
return new class extends Migration
{
    private const KEY = 'company_contact_role';

    private const OLD = [
        'md_one' => 'Managing Director', 'md_second' => 'Managing Director',
        'hr_head' => 'HR Head', 'assistant_hr' => 'Assistant HR',
        'accountant_head' => 'Accountant Head', 'accountant_assistant_one' => 'Accountant Assistant',
        'accountant_assistant_two' => 'Accountant Assistant',
    ];

    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->json('contacts')->nullable()->after('gst_number');
        });

        $now = now();
        $setId = DB::table('option_sets')->where('key', self::KEY)->value('id') ?? DB::table('option_sets')->insertGetId([
            'key' => self::KEY, 'name' => 'Company Contact Roles',
            'description' => 'What a person is to a company, on Company Profiles',
            'icon' => 'bi-person-badge', 'input' => 'select', 'is_system' => true,
            'sort_order' => (int) DB::table('option_sets')->max('sort_order') + 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach (['Managing Director', 'HR Head', 'Assistant HR', 'Accountant Head', 'Accountant Assistant', 'Other'] as $position => $label) {
            DB::table('option_items')->insertOrIgnore([
                'option_set_id' => $setId, 'label' => $label, 'value' => $label, 'is_active' => true,
                'is_default' => $position === 0, 'sort_order' => $position + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (DB::table('company_profiles')->get() as $company) {
            $people = [];
            foreach (self::OLD as $slot => $role) {
                $name = $company->{"{$slot}_name"} ?? null;
                $email = $company->{"{$slot}_email"} ?? null;
                $mobile = $company->{"{$slot}_mobile"} ?? null;

                if (filled($name) || filled($email) || filled($mobile)) {
                    $people[] = ['role' => $role, 'name' => $name, 'email' => $email, 'mobile' => $mobile];
                }
            }

            if ($people) {
                DB::table('company_profiles')->where('id', $company->id)->update(['contacts' => json_encode($people)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('company_profiles', fn (Blueprint $table) => $table->dropColumn('contacts'));
        DB::table('option_sets')->where('key', self::KEY)->delete();
    }
};
