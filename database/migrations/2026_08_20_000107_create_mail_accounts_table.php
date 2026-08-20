<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('gmail_address');
            $table->text('app_password');
            $table->string('smtp_host')->default('smtp.gmail.com');
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->unsignedSmallInteger('throttle_seconds')->default(4);
            $table->unsignedInteger('daily_send_cap')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
