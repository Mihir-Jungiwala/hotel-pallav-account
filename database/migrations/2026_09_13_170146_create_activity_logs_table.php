<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('forgot_password_token', 100)->nullable();
            $table->boolean('token_used')->default(false);
            $table->dateTime('email_sent_time')->nullable();
            $table->enum('activity_type', ['Login', 'Logout']);
            $table->dateTime('activity_time')->useCurrent();
            $table->date('login_date')->nullable();
            $table->date('logout_date')->nullable();
            $table->time('login_time')->nullable();
            $table->time('logout_time')->nullable();
            $table->string('minutes_logged_in', 5)->nullable();
            $table->dateTime('password_change_time')->nullable();
            $table->unsignedInteger('password_change_duration')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
