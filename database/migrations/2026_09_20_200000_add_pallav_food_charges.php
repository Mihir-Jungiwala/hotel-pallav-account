<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Hotel Pallav staff who eat at Pallav Food; the owner pays for those meals
            $table->boolean('eats_at_pallav_food')->default(false);
        });

        // The fixed monthly charge Pallav Food sets, with the month it starts,
        // so changing it never rewrites the reports of earlier months
        Schema::create('food_charge_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_company_id')->constrained('payroll_companies')->cascadeOnDelete();
            $table->decimal('monthly_amount', 10, 2);
            $table->date('effective_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payroll_company_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_charge_rates');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('eats_at_pallav_food');
        });
    }
};
