<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Free-text specific posting/charge (e.g. "Deputy Excise Commissioner (Prevention
            // & Enforcement)"), distinct from designation_id's standard rank — same split as
            // ~/Sites/pdf-markdown-pipeline's users.post column.
            $table->string('post', 100)->nullable()->after('designation_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('post');
        });
    }
};
