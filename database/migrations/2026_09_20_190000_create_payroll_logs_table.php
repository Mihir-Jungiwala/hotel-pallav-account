<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_logs', function (Blueprint $table) {
            $table->id();
            // Null for things that belong to no single company
            $table->foreignId('payroll_company_id')->nullable()->constrained('payroll_companies')->nullOnDelete();
            $table->string('company_name', 150)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Names are kept as written, so the log still reads correctly after a rename or delete
            $table->string('user_name', 150)->nullable();
            $table->string('action', 30);
            $table->string('status', 10)->default('success');
            $table->string('entity', 60);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 255)->nullable();
            $table->string('summary', 500);
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payroll_company_id', 'created_at']);
            $table->index(['entity', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_logs');
    }
};
