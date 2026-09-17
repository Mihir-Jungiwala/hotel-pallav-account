<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_master_bills', function (Blueprint $table) {
            $table->id();

            // Advance snapshot (denormalized, as in the source app)
            $table->foreignId('advance_id')->nullable()->constrained('bill_master_advances')->nullOnDelete();
            $table->string('advance_receipt_number', 100)->nullable();
            $table->string('advance_guest_name', 100)->nullable();
            $table->string('advance_hotel_amount_snapshot', 100)->nullable();
            $table->string('advance_date_snapshot', 100)->nullable();
            $table->string('advance_food_amount_snapshot', 100)->nullable();
            $table->string('advance_company_snapshot', 100)->nullable();

            $table->string('bill_number', 100)->unique()->nullable();
            $table->foreignId('company_id')->nullable()->constrained('company_profiles')->cascadeOnDelete();
            $table->date('bill_date')->nullable();
            $table->string('guest_name', 255)->nullable();
            $table->string('mobile_number', 15)->nullable();

            // Hotel side
            $table->string('hotel_plan', 100)->nullable();
            $table->decimal('hotel_amount', 10, 2)->default(0);
            $table->decimal('hotel_plan_amount', 10, 2)->default(0);
            $table->decimal('hotel_laundry_amount', 10, 2)->default(0);
            $table->decimal('hotel_gst', 10, 2)->default(0);
            $table->string('hotel_mode_of_payment', 100)->nullable();

            // Food side
            $table->decimal('food_plan_amount', 10, 2)->default(0);
            $table->decimal('food_laundry_amount', 10, 2)->default(0);
            $table->decimal('food_amount', 10, 2)->default(0);
            $table->decimal('food_gst', 10, 2)->default(0);
            $table->string('food_mode_of_payment', 100)->nullable();

            $table->decimal('total_hotel_amount', 10, 2)->default(0);
            $table->decimal('total_food_amount', 10, 2)->default(0);

            $table->string('reference_name', 100)->nullable();
            $table->string('reference_mobile_number', 15)->nullable();
            $table->text('instruction')->nullable();
            $table->string('invoice_pdf_path')->nullable();

            $table->decimal('balance_hotel_amount', 10, 2)->default(0);
            $table->decimal('balance_food_amount', 10, 2)->default(0);
            $table->decimal('advance_hotel_amount', 10, 2)->default(0);
            $table->decimal('advance_food_amount', 10, 2)->default(0);
            $table->decimal('advance_delete_hotel_amount', 10, 2)->default(0);
            $table->decimal('advance_delete_food_amount', 10, 2)->default(0);
            $table->string('formatted_advance_receipt_number', 255)->nullable();

            // Debit / installment slots (base + 1..4 = 5 total)
            foreach (range(0, 4) as $i) {
                $suffix = $i === 0 ? '' : "_$i";
                $table->date("debit_bill_date{$suffix}")->nullable();
                $table->string("debit_hotel_mode{$suffix}", 20)->nullable();
                $table->string("debit_food_mode{$suffix}", 20)->nullable();
                $table->decimal("debit_hotel_amount{$suffix}", 10, 2)->nullable();
                $table->decimal("debit_food_amount{$suffix}", 10, 2)->nullable();
            }

            $table->string('debit_hotel_advance', 100)->nullable();
            $table->string('debit_food_advance', 100)->nullable();
            $table->string('debit_reference_name', 100)->nullable();
            $table->string('debit_reference_mobile_number', 15)->nullable();
            $table->text('debit_instruction')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_master_bills');
    }
};
