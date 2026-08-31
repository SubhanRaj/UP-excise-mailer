<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matches MailAccount::SEND_COOLDOWN_SECONDS, the floor now enforced at send time
     * regardless of this column — bumping existing rows here just keeps the configured value
     * from misleadingly reading lower than what actually happens.
     */
    public function up(): void
    {
        DB::table('mail_accounts')->where('throttle_seconds', '<', 60)->update(['throttle_seconds' => 60]);

        Schema::table('mail_accounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('throttle_seconds')->default(60)->change();
        });
    }

    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('throttle_seconds')->default(4)->change();
        });
    }
};
