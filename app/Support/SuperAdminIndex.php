<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * "Only one live SuperAdmin" is enforced by a partial unique index. SQLite
 * rebuilds a table whenever a column is added, and a rebuilt index loses its
 * WHERE clause, which would quietly turn the rule into "only one of each
 * role". Any migration that touches the users table calls this afterwards.
 */
class SuperAdminIndex
{
    public const NAME = 'users_single_superadmin';

    public const CONDITION = "role = 'SuperAdmin' AND deleted_at IS NULL";

    public static function ensure(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS '.self::NAME);
        DB::statement('CREATE UNIQUE INDEX '.self::NAME.' ON users (role) WHERE '.self::CONDITION);
    }

    /** True when the index exists and still carries its condition. */
    public static function isPartial(): bool
    {
        if (DB::getDriverName() !== 'sqlite') {
            return true;
        }

        $sql = DB::table('sqlite_master')->where('name', self::NAME)->value('sql');

        return $sql !== null && str_contains($sql, 'WHERE') && str_contains($sql, "role = 'SuperAdmin'");
    }
}
