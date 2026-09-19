<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A mobile number is now a country code plus the national digits, so staff
 * and their families abroad can be reached. Every number stored until now is
 * Indian, which is exactly what the default of 91 says: nothing existing
 * changes meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('contact_country', 4)->default('91');
            $table->string('emergency_contact_country', 4)->default('91');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['contact_country', 'emergency_contact_country']);
        });
    }
};
