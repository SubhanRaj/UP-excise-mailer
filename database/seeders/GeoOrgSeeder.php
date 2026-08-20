<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Division;
use App\Models\Zone;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the 5 zones, 18 divisions, and 75 districts of the department's
 * administrative hierarchy, with real DEO/DC/JC contact details.
 *
 * Source: copied verbatim from ~/Sites/excise-budget-tracker's seeders/data —
 * already the cleaned join of ~/Projects/excise-revenue-recovery-portal's
 * contact.csv/emails.csv and ~/Projects/up-excise-spatial-revenue-optimizer's
 * district geojson centroids. Contact numbers are plain office CUG numbers,
 * not credentials — no hashing needed.
 */
class GeoOrgSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $dataPath = database_path('seeders/data');

        $zones = json_decode(file_get_contents("{$dataPath}/zones.json"), true);
        foreach ($zones as $zone) {
            Zone::firstOrCreate(['name' => $zone['name']], [
                'jc_name' => $zone['jc_name'],
                'jc_email' => $zone['jc_email'],
                'jc_cug' => $zone['jc_cug'],
            ]);
        }

        $divisions = json_decode(file_get_contents("{$dataPath}/divisions.json"), true);
        foreach ($divisions as $division) {
            Division::firstOrCreate(['name' => $division['name']], [
                'zone_id' => Zone::where('name', $division['zone'])->value('id'),
                'dc_name' => $division['dc_name'],
                'dc_email' => $division['dc_email'],
                'dc_cug' => $division['dc_cug'],
            ]);
        }

        $districts = json_decode(file_get_contents("{$dataPath}/districts.json"), true);
        foreach ($districts as $district) {
            District::firstOrCreate(['name' => $district['name']], [
                'division_id' => Division::where('name', $district['division'])->value('id'),
                'deo_name' => $district['deo_name'],
                'deo_email' => $district['deo_email'],
                'deo_cug' => $district['deo_cug'],
                'latitude' => $district['latitude'],
                'longitude' => $district['longitude'],
            ]);
        }
    }
}
