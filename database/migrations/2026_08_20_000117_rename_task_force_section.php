<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: the live DB already has a "Task Force" row (seeded before this
 * rename); update it in place rather than relying on SectionSeeder re-running.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sections')->where('slug', 'task-force')->update([
            'name' => 'Task Force Section',
            'slug' => 'task-force-section',
        ]);
    }

    public function down(): void
    {
        DB::table('sections')->where('slug', 'task-force-section')->update([
            'name' => 'Task Force',
            'slug' => 'task-force',
        ]);
    }
};
