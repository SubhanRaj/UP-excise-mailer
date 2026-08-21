<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table) {
            // Nullable — reply fetching is opt-in per account. Port 993 implies SSL (mirrors
            // the existing smtp_port 465-implies-ssl convention), so no separate encryption column.
            $table->string('imap_host')->nullable()->after('smtp_port');
            $table->unsignedSmallInteger('imap_port')->nullable()->after('imap_host');
            $table->timestamp('imap_last_fetched_at')->nullable()->after('imap_port');
        });
    }

    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table) {
            $table->dropColumn(['imap_host', 'imap_port', 'imap_last_fetched_at']);
        });
    }
};
