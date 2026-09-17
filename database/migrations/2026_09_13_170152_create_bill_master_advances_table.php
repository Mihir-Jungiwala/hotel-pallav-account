<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_master_advances', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 100)->unique()->nullable();
            $table->string('guest_name', 255)->nullable();
            $table->string('mobile_number', 15)->nullable();
            $table->foreignId('company_id')->nullable()->constrained('company_profiles')->cascadeOnDelete();
            $table->date('payment_date')->nullable();
            $table->decimal('hotel_amount', 20, 2)->nullable();
            $table->decimal('food_amount', 20, 2)->nullable();
            $table->string('hotel_mode', 20)->nullable();
            $table->string('food_mode', 20)->nullable();
            $table->string('reference_name', 255)->nullable();
            $table->string('reference_mobile_number', 15)->nullable();
            $table->string('instruction', 255)->nullable();
            $table->decimal('hotel_balance', 50, 2)->default(0);
            $table->decimal('food_balance', 50, 2)->default(0);
            $table->decimal('total', 50, 2)->default(0);

            $table->decimal('hotel_refund_amount', 20, 2)->nullable();
            $table->decimal('food_refund_amount', 20, 2)->nullable();
            $table->string('hotel_refund_mode', 20)->nullable();
            $table->string('food_refund_mode', 20)->nullable();
            $table->date('refund_payment_date')->nullable();
            $table->string('refund_guest_name', 255)->nullable();
            $table->string('refund_mobile_number', 15)->nullable();
            $table->string('refund_instruction', 255)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_master_advances');
    }
};
