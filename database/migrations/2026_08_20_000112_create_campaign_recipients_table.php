<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_type'); // zone | division | district | list_item
            $table->unsignedBigInteger('recipient_ref_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email');
            $table->string('attachment_path')->nullable();
            $table->string('matched_via')->nullable(); // filename_auto | manual | none
            $table->string('status')->default('pending'); // pending | queued | sent | failed
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
