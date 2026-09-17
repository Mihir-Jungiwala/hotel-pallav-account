<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('address', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('pincode', 20)->nullable();
            $table->string('mobile_number', 15)->nullable();
            $table->string('phone_number', 15)->nullable();
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->decimal('gst_percentage', 5, 2)->nullable();
            $table->decimal('tcs_percentage', 5, 2)->nullable();
            $table->decimal('tds_percentage', 5, 2)->nullable();
            $table->text('instruction')->nullable();
            $table->string('gst_number', 50)->nullable();

            foreach ([
                'md_one', 'md_second', 'hr_head', 'assistant_hr',
                'accountant_head', 'accountant_assistant_one', 'accountant_assistant_two',
            ] as $contact) {
                $table->string("{$contact}_name", 100)->nullable();
                $table->string("{$contact}_email", 254)->nullable();
                $table->string("{$contact}_mobile", 15)->nullable();
            }

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('modified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
