<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(DesignationSeeder::class);
        $this->call(GeoOrgSeeder::class);
        $this->call(SectionSeeder::class);
    }
}
