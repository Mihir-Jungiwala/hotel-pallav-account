<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->after('name');
            $table->string('phone', 20)->nullable()->after('email');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('password_changed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Email is optional now that login is by username; it is only used for resets
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        // Give every existing account a unique username derived from its email/name
        $taken = [];
        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $base = $user->role === 'SuperAdmin'
                ? 'superadmin'
                : Str::of($user->email ? Str::before($user->email, '@') : $user->name)->lower()->replaceMatches('/[^a-z0-9._]/', '')->limit(40, '')->value();

            $base = $base ?: 'user';
            $candidate = $base;
            $n = 1;
            while (in_array($candidate, $taken, true)) {
                $candidate = $base.(++$n);
            }
            $taken[] = $candidate;

            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });

        // The database itself refuses a second SuperAdmin
        // (a partial index is SQLite/Postgres syntax; on MySQL the later
        // restore_single_superadmin_index migration builds it with a generated column)
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX users_single_superadmin ON users (role) WHERE role = 'SuperAdmin'");
        }

        Schema::create('user_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Kept even if the account is later deleted
            $table->string('target_username', 50)->nullable();
            $table->string('action', 50);
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_audit_logs');
        DB::statement('DROP INDEX IF EXISTS users_single_superadmin');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'username', 'phone', 'last_login_at', 'last_login_ip', 'failed_login_attempts',
                'locked_until', 'must_change_password', 'password_changed_at',
            ]);
        });
    }
};
