<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Only one live SuperAdmin" is enforced by the database itself, not just by
 * the code, so no race or stray script can create a second one.
 *
 * SQLite does it with a partial unique index. MySQL has no partial index, so
 * it uses a generated column that is 1 for the live SuperAdmin and NULL for
 * everyone else, with a unique index on it: NULLs do not collide, so any
 * number of other rows are fine.
 *
 * SQLite also rebuilds a table whenever a column is added, and a rebuilt index
 * loses its WHERE clause, so every migration that touches users calls ensure()
 * afterwards.
 */
class SuperAdminIndex
{
    public const NAME = 'users_single_superadmin';

    public const COLUMN = 'is_the_superadmin';

    public const CONDITION = "role = 'SuperAdmin' AND deleted_at IS NULL";

    public static function ensure(): void
    {
        match (DB::getDriverName()) {
            'sqlite' => self::ensureSqlite(),
            'mysql', 'mariadb' => self::ensureMysql(),
            'pgsql' => self::ensurePostgres(),
            default => null,
        };
    }

    private static function ensureSqlite(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::NAME);
        DB::statement('CREATE UNIQUE INDEX '.self::NAME.' ON users (role) WHERE '.self::CONDITION);
    }

    private static function ensureMysql(): void
    {
        if (! Schema::hasColumn('users', self::COLUMN)) {
            DB::statement(
                'ALTER TABLE users ADD COLUMN '.self::COLUMN.' TINYINT(1) '
                .'GENERATED ALWAYS AS (CASE WHEN '.self::CONDITION.' THEN 1 ELSE NULL END) STORED'
            );
        }

        if (! self::hasMysqlIndex()) {
            DB::statement('ALTER TABLE users ADD CONSTRAINT '.self::NAME.' UNIQUE ('.self::COLUMN.')');
        }
    }

    private static function ensurePostgres(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::NAME);
        DB::statement('CREATE UNIQUE INDEX '.self::NAME.' ON users (role) WHERE '.self::CONDITION);
    }

    private static function hasMysqlIndex(): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'users')
            ->where('index_name', self::NAME)
            ->exists();
    }

    /** True when the rule is in place on this connection. */
    public static function isPartial(): bool
    {
        return match (DB::getDriverName()) {
            'sqlite' => self::sqliteIndexIsPartial(),
            'mysql', 'mariadb' => Schema::hasColumn('users', self::COLUMN) && self::hasMysqlIndex(),
            default => true,
        };
    }

    private static function sqliteIndexIsPartial(): bool
    {
        $sql = DB::table('sqlite_master')->where('name', self::NAME)->value('sql');

        return $sql !== null && str_contains($sql, 'WHERE') && str_contains($sql, "role = 'SuperAdmin'");
    }
}
