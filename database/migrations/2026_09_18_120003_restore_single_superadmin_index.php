<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SQLite rebuilds a table when a column is added, and a rebuilt index loses its
 * WHERE clause, which turned "only one SuperAdmin" into "only one of each
 * role". This puts the partial index back, and scopes it to live accounts so a
 * closed SuperAdmin record never blocks the next one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS users_single_superadmin');
            DB::statement("CREATE UNIQUE INDEX users_single_superadmin ON users (role) WHERE role = 'SuperAdmin' AND deleted_at IS NULL");

            return;
        }

        // MySQL has no partial index: a generated column plus a unique index does the same job
        \App\Support\SuperAdminIndex::ensure();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS users_single_superadmin');
        }
    }
};
