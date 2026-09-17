<?php

namespace Database\Seeders;

use App\Models\IdProofType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The one SuperAdmin. Only created if none exists, so re-seeding never
        // collides with a role that has since been transferred.
        if (! User::where('role', User::ROLE_SUPERADMIN)->exists()) {
            User::create([
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'email' => 'superadmin@hotelpallav.com',
                'password' => Hash::make('SuperAdmin@123'),
                'role' => User::ROLE_SUPERADMIN,
                'is_active' => true,
                // Forces a real password on first sign-in outside local development
                'must_change_password' => app()->isProduction(),
                'password_changed_at' => now(),
            ]);
        }

        foreach (['Aadhaar Card', 'PAN Card', 'Voter ID', 'Driving Licence', 'Passport'] as $name) {
            IdProofType::updateOrCreate(['name' => $name], ['is_active' => true]);
        }

        // Demo data never goes into a production database
        if (! app()->isProduction()) {
            $this->call(MockDataSeeder::class);
        }
    }
}
