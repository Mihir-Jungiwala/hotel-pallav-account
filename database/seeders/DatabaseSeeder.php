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
        User::updateOrCreate(
            ['email' => 'superadmin@hotelpallav.com'],
            [
                'name' => 'SuperAdmin',
                'password' => Hash::make('SuperAdmin@123'),
                'role' => 'SuperAdmin',
                'is_active' => true,
            ]
        );

        foreach (['Aadhaar Card', 'PAN Card', 'Voter ID', 'Driving Licence', 'Passport'] as $name) {
            IdProofType::updateOrCreate(['name' => $name], ['is_active' => true]);
        }

        $this->call(MockDataSeeder::class);
    }
}
