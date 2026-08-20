<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('mobile', 10)->nullable()->after('email');
            $table->string('role')->default('User')->after('mobile');
            $table->foreignId('designation_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->after('designation_id')->constrained()->nullOnDelete();
            $table->json('privileges')->nullable()->after('section_id');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designation_id');
            $table->dropConstrainedForeignId('section_id');
            $table->dropColumn(['username', 'mobile', 'role', 'privileges', 'deleted_at']);
        });
    }
};
