<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * The roles carried over from the legacy attendance app.
     *
     * @var array<int, string>
     */
    public const ROLES = [
        'Super Admin',
        'Admin',
        'HR',
        'CEO',
        'Employee',
        'branch_manager',
        'diputy',
        'Pas Director',
    ];

    public function run(): void
    {
        // Forget the cached roles/permissions so freshly created rows are seen.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
