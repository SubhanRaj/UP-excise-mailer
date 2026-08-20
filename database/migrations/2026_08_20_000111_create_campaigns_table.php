<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('mail_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('mail_templates')->nullOnDelete();
            $table->string('subject');
            $table->longText('body');
            $table->string('recipient_scope'); // all | zones | divisions | districts | recipient_list
            $table->string('attachment_mode')->default('none'); // single_file | zip_per_recipient | none
            $table->string('status')->default('draft'); // draft | queued | sending | completed | failed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
