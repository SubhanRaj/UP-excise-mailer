<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The jc_name/dc_name/deo_name values seeded from excise-budget-tracker's JSON export are
     * stale (officers rotate postings regularly) and were in Hindi, which doesn't round-trip
     * cleanly through the mail-merge {{officer}} variable. Email/CUG are left untouched — only
     * the name was flagged as unreliable. Zone/Division/District::officerDisplayName() covers
     * the blank-name UI fallback ("DEO - Agra" etc.) until real names are re-entered via the
     * edit forms or the officer-directory XLSX import.
     */
    public function up(): void
    {
        DB::table('zones')->update(['jc_name' => null]);
        DB::table('divisions')->update(['dc_name' => null]);
        DB::table('districts')->update(['deo_name' => null]);
    }

    public function down(): void
    {
        // Not reversible — the original values were stale anyway.
    }
};
