<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An emergency contact is on every standard employee record and was the one
 * thing the staff form had nowhere to keep. All three are optional: nothing
 * about an existing employee changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_relation', 60)->nullable();
            $table->string('emergency_contact_number', 15)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_number']);
        });
    }
};
