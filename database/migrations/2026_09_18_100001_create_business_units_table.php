<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hotel Pallav and Pallav Food run as separate businesses that share one
 * database: cash, expenses, bills and handovers all belong to one side or the
 * other, while bills legitimately carry both amounts on a single record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code', 10)->unique();
            $table->string('accent', 7)->default('#7C3AED');
            $table->string('icon', 40)->default('bi-buildings');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('business_units')->insert([
            [
                'name' => 'Hotel Pallav', 'slug' => 'hotel', 'code' => 'HP',
                'accent' => '#7C3AED', 'icon' => 'bi-building', 'is_active' => true,
                'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Pallav Food', 'slug' => 'food', 'code' => 'PF',
                'accent' => '#0F766E', 'icon' => 'bi-cup-hot', 'is_active' => true,
                'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        $hotelId = DB::table('business_units')->where('slug', 'hotel')->value('id');

        // Records with no natural side default to Hotel Pallav and can be moved
        foreach (['staff_advances', 'shift_handovers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('business_unit_id')->nullable()->after('id')
                    ->constrained('business_units')->nullOnDelete();
            });

            DB::table($table)->update(['business_unit_id' => $hotelId]);
        }
    }

    public function down(): void
    {
        foreach (['staff_advances', 'shift_handovers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('business_unit_id');
            });
        }

        Schema::dropIfExists('business_units');
    }
};
