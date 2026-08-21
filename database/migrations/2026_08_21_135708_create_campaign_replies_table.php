<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_recipient_id')->constrained()->cascadeOnDelete();
            $table->string('message_id')->unique();
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();
            $table->text('body_text')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_replies');
    }
};
