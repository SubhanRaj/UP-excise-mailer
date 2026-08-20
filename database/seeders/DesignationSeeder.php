<?php

namespace Database\Seeders;

use App\Models\Designation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DesignationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * UP Government designation ladder, ported from ~/Sites/excise-budget-tracker
     * and ~/Sites/pdf-markdown-pipeline's DesignationSeeders and remapped onto
     * this app's own User::PRIVILEGES.
     *
     * @return list<array{name: string, default_privileges: array<int, string>}>
     */
    private function designations(): array
    {
        return [
            ['name' => 'Excise Commissioner', 'default_privileges' => ['*']],
            ['name' => 'Additional Excise Commissioner', 'default_privileges' => ['*']],
            ['name' => 'Joint Excise Commissioner', 'default_privileges' => ['campaigns.send', 'templates.manage']],
            ['name' => 'Deputy Excise Commissioner', 'default_privileges' => ['campaigns.send', 'templates.manage']],
            ['name' => 'Assistant Excise Commissioner', 'default_privileges' => ['campaigns.send']],
            ['name' => 'District Excise Officer', 'default_privileges' => ['campaigns.send']],
            ['name' => 'Section Officer', 'default_privileges' => ['campaigns.send', 'recipients.import']],
            ['name' => 'Clerk', 'default_privileges' => ['campaigns.send']],
            ['name' => 'System Engineer', 'default_privileges' => ['*']],
        ];
    }

    public function run(): void
    {
        foreach ($this->designations() as $sortOrder => $designation) {
            Designation::firstOrCreate(
                ['name' => $designation['name']],
                [
                    'slug' => Str::slug($designation['name']),
                    'default_privileges' => $designation['default_privileges'],
                    'sort_order' => $sortOrder,
                ]
            );
        }
    }
}
