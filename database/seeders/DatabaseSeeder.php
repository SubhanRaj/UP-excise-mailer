<?php

namespace Database\Seeders;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(DesignationSeeder::class);
        $this->call(GeoOrgSeeder::class);

        User::firstOrCreate(
            ['email' => 'redacted-personal-email@example.com'],
            [
                'name' => 'Subhan Raj',
                'username' => User::uniqueUsername('Subhan Raj'),
                'password' => Hash::make('REDACTED-PASSWORD'),
                'email_verified_at' => now(),
                'role' => 'SuperAdmin',
                'designation_id' => Designation::where('name', 'System Engineer')->first()?->id,
            ]
        );
    }
}
