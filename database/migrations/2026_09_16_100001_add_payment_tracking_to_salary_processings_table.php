<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen the old Pending/Paid enum so more payment states can be tracked
        Schema::table('salary_processings', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('Pending')->change();
        });

        Schema::table('salary_processings', function (Blueprint $table) {
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->date('paid_at')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->text('payment_remarks')->nullable();
            $table->foreignId('payment_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('payment_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('salary_processings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_updated_by');
            $table->dropColumn(['paid_amount', 'paid_at', 'payment_reference', 'payment_remarks', 'payment_updated_at']);
        });
    }
};
