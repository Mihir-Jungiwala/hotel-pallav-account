<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_companies', function (Blueprint $table) {
            $table->id();

            // Basic information
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->string('logo_path')->nullable();
            $table->string('owner_name', 150)->nullable();
            $table->string('mobile_number', 15)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 20)->nullable();

            // Legal & registration
            $table->string('pan_number', 20)->nullable();
            $table->string('tan_number', 20)->nullable();
            $table->string('pf_registration_number', 50)->nullable();
            $table->string('esic_registration_number', 50)->nullable();
            $table->string('professional_tax_registration_number', 50)->nullable();

            // Authorized signatory
            $table->string('authorized_person_name', 150)->nullable();
            $table->string('authorized_designation', 100)->nullable();
            $table->string('authorized_mobile', 15)->nullable();
            $table->string('authorized_email', 254)->nullable();
            $table->string('signature_image_path')->nullable();

            // Banking
            $table->string('bank_name', 150)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('ifsc_code', 20)->nullable();
            $table->string('branch_name', 150)->nullable();

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_companies');
    }
};
