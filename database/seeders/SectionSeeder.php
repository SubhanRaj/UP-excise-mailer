<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SectionSeeder extends Seeder
{
    /**
     * Same HQ section list as ~/Sites/pdf-markdown-pipeline's SectionSeeder, HQ sections
     * only — that app's "Joint/Deputy Secretary Wing" entries belong to a separate
     * `Department` row (slug `excise`, level `secretariat_level`) from the actual Excise
     * Department (level `department_level`) whose sections these are; don't conflate the
     * two even though they share a slug there.
     */
    public function run(): void
    {
        $sections = [
            'Establishment Section',
            'Accounts Section',
            'Audit Section',
            'Statistics Section',
            'License Section',
            'Technical Section',
            'Molasses Section',
            'Alcohol Section',
            'Excise Intelligence Bureau',
            'Legal Section',
            'Task Force Section',
        ];

        foreach ($sections as $name) {
            Section::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}
