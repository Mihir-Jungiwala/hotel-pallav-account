<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_master_items', function (Blueprint $table) {
            $table->id();
            // Null means the item applies to every company; otherwise to that one only
            $table->foreignId('payroll_company_id')->nullable()->constrained('payroll_companies')->cascadeOnDelete();
            $table->string('list', 40);
            $table->string('label', 120);
            $table->string('value', 190)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['list', 'payroll_company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_master_items');
    }
};
