<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two separate ideas, kept apart:
 *  - five wrong passwords or codes BLOCK the account. It stays blocked until an
 *    administrator unlocks it, or the owner proves the email is theirs by
 *    resetting the password with a code.
 *  - asking for too many emails starts a WAIT, which gets longer each run and
 *    tops out at 24 hours. The account is not blocked, it just has to wait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('locked_until');
            $table->string('blocked_reason', 120)->nullable()->after('blocked_at');

            $table->timestamp('otp_cooldown_until')->nullable()->after('otp_sends');
            $table->unsignedTinyInteger('otp_cooldown_level')->default(0)->after('otp_cooldown_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['blocked_at', 'blocked_reason', 'otp_cooldown_until', 'otp_cooldown_level']);
        });
    }
};
