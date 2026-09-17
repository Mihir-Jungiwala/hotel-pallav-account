<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('joining_letters', function (Blueprint $table) {
            $table->id();
            // Only one template per company
            $table->foreignId('payroll_company_id')->unique()->constrained('payroll_companies')->cascadeOnDelete();

            $table->string('subject', 255)->nullable();
            $table->text('introduction_content')->nullable();
            $table->text('roles_responsibilities')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->text('closing_message')->nullable();

            $table->boolean('use_company_signatory')->default(true);
            $table->string('authorized_name', 150)->nullable();
            $table->string('authorized_designation', 100)->nullable();
            $table->string('signature_image_path')->nullable();
            $table->text('authorized_closing_text')->nullable();

            $table->string('acceptance_heading', 255)->nullable();
            $table->text('acceptance_content')->nullable();
            $table->text('acceptance_closing_text')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('joining_letters');
    }
};
