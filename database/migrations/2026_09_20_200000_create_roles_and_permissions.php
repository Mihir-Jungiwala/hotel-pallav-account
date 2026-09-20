<?php

use App\Models\User;
use App\Support\SuperAdminIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles become records instead of four names in the code, so the ladder can be
 * reordered and the permissions behind each rung changed on screen.
 *
 * The four roles that exist are created with exactly what they could already
 * do, so nothing about today's access changes until somebody edits it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Each piece is checked first: a part-applied database (a run that
        // stopped half way) can be finished off rather than unpicked by hand.
        if (! Schema::hasTable('roles')) {
            $this->createRoles();
        }
        if (! Schema::hasTable('role_permissions')) {
            $this->createRolePermissions();
        }
        if (! Schema::hasTable('user_permissions')) {
            $this->createUserPermissions();
        }
        if (! Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
            });

            // SQLite rebuilds the whole table to add a column, which drops the
            // partial index behind "there is only ever one SuperAdmin"
            SuperAdminIndex::ensure();
        }
        if (DB::table('roles')->count() === 0) {
            $this->seedRoles();
        }
    }

    private function createRoles(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                  // stable name used in code
            $table->string('name');                           // what people see
            $table->string('description', 500)->nullable();
            $table->string('icon', 40)->default('bi-person');
            $table->string('accent', 7)->default('#7C3AED');
            $table->unsignedSmallInteger('level');            // higher outranks lower
            $table->boolean('inherits')->default(true);       // gains everything below it
            $table->boolean('is_system')->default(false);     // cannot be deleted or renamed away
            $table->timestamps();
        });
    }

    private function createRolePermissions(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->unique(['role_id', 'permission']);
        });
    }

    /** One person given something extra, or having something taken away. */
    private function createUserPermissions(): void
    {
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->boolean('granted');                       // false = taken away
            $table->string('reason', 255)->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'permission']);
        });
    }

    /** The four roles as they behave today, each owning what it adds. */
    private function seedRoles(): void
    {
        $now = now();

        $roles = [
            [
                'key' => User::ROLE_VIEWER, 'name' => 'Viewer', 'level' => 1,
                'icon' => 'bi-eye', 'accent' => '#64748B',
                'description' => 'Reads every screen and report, and changes nothing.',
                'permissions' => [
                    'dashboard.view',
                    'revenue.view', 'revenue.export',
                    'expense.view', 'expense.export',
                    'handover.view', 'handover.export',
                    'bills.view', 'bills.export',
                    'companies.view',
                    'payroll.view', 'payroll.export',
                    'reports.view', 'reports.export',
                ],
            ],
            [
                'key' => User::ROLE_EDITOR, 'name' => 'Editor', 'level' => 2,
                'icon' => 'bi-pencil-square', 'accent' => '#0F766E',
                'description' => 'Records the day\'s work and corrects it. Cannot delete or manage accounts.',
                'permissions' => [
                    'revenue.create', 'revenue.edit',
                    'expense.create', 'expense.edit',
                    'handover.create', 'handover.edit',
                    'bills.create', 'bills.edit',
                    'companies.create', 'companies.edit',
                    'payroll.create', 'payroll.edit',
                ],
            ],
            [
                'key' => User::ROLE_ADMIN, 'name' => 'Admin', 'level' => 3,
                'icon' => 'bi-person-gear', 'accent' => '#B45309',
                'description' => 'Runs the day to day. Deletes records, manages Editors and Viewers.',
                'permissions' => [
                    'revenue.delete', 'expense.delete', 'handover.delete', 'bills.delete',
                    'companies.delete', 'payroll.delete',
                    'users.view', 'users.create', 'users.edit', 'users.delete',
                    'users.password', 'users.activity',
                    'access.view',
                    'system.backdate',
                ],
            ],
            [
                'key' => User::ROLE_SUPERADMIN, 'name' => 'SuperAdmin', 'level' => 4,
                'icon' => 'bi-shield-fill-check', 'accent' => '#6D28D9',
                'description' => 'Owns the system. The only role that can change roles, Master Data and force mode.',
                'permissions' => [
                    'access.edit',
                    'masters.view', 'masters.edit',
                    'system.force',
                ],
            ],
        ];

        foreach ($roles as $role) {
            $id = DB::table('roles')->insertGetId([
                'key' => $role['key'], 'name' => $role['name'], 'description' => $role['description'],
                'icon' => $role['icon'], 'accent' => $role['accent'], 'level' => $role['level'],
                'inherits' => true, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);

            DB::table('role_permissions')->insert(array_map(
                fn ($permission) => ['role_id' => $id, 'permission' => $permission],
                $role['permissions']
            ));

            DB::table('users')->where('role', $role['key'])->update(['role_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
