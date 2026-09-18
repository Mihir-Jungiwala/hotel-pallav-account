<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The personal and qualification details the old staff profile carried:
 * date of birth, gender, nationality, schooling, skills, a resume and the back
 * of the ID proof.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('email', 254)->nullable()->after('contact_number');
            $table->date('date_of_birth')->nullable()->after('email');
            $table->string('gender', 20)->nullable()->after('date_of_birth');
            $table->string('nationality', 100)->nullable()->after('gender');
            $table->string('country_and_pincode', 100)->nullable()->after('nationality');
            $table->string('qualification', 150)->nullable()->after('country_and_pincode');
            $table->string('qualification_institution', 150)->nullable()->after('qualification');
            $table->text('skills')->nullable()->after('qualification_institution');
            $table->string('resume_path')->nullable()->after('id_proof_image_path');
            $table->string('id_proof_back_image_path')->nullable()->after('resume_path');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'email', 'date_of_birth', 'gender', 'nationality', 'country_and_pincode',
                'qualification', 'qualification_institution', 'skills',
                'resume_path', 'id_proof_back_image_path',
            ]);
        });
    }
};
