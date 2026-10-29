<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsvp_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitee_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['yes', 'no']);
            $table->unsignedTinyInteger('guests')->default(0);
            $table->json('guest_names')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsvp_logs');
    }
};
