<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign-in hardening:
 *  - a one time code by email, both as the second step of a sign-in and as the
 *    way a forgotten password is reset
 *  - lockouts that grow each time, capped at 24 hours
 *  - one live session per account, so a second sign-in ends the first
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The code itself is only ever stored as a hash
            $table->string('otp_hash')->nullable()->after('locked_until');
            $table->string('otp_purpose', 20)->nullable()->after('otp_hash');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_purpose');
            $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_expires_at');
            $table->timestamp('otp_sent_at')->nullable()->after('otp_attempts');
            $table->unsignedTinyInteger('otp_sends')->default(0)->after('otp_sent_at');

            // How many times this account has been locked, so each lock lasts longer
            $table->unsignedTinyInteger('lock_level')->default(0)->after('otp_sends');

            // The single session this account is allowed to hold
            $table->string('current_session_id', 100)->nullable()->after('lock_level');
            $table->timestamp('session_started_at')->nullable()->after('current_session_id');
            $table->boolean('two_factor_enabled')->default(true)->after('session_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'otp_hash', 'otp_purpose', 'otp_expires_at', 'otp_attempts', 'otp_sent_at', 'otp_sends',
                'lock_level', 'current_session_id', 'session_started_at', 'two_factor_enabled',
            ]);
        });
    }
};
