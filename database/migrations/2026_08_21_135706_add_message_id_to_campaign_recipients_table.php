<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            // The outgoing Message-ID header, captured at send time — lets a reply be matched
            // back to this exact recipient via IMAP's In-Reply-To/References headers.
            $table->string('message_id')->nullable()->after('sent_at');
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropIndex(['message_id']);
            $table->dropColumn('message_id');
        });
    }
};
