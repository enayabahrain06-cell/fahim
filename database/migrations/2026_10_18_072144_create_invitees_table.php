<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitees', function (Blueprint $table) {
            $table->id();
            $table->string('token', 16)->unique();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable();
            $table->unsignedTinyInteger('seats')->default(1);
            $table->enum('status', ['pending', 'yes', 'no'])->default('pending')->index();
            $table->unsignedTinyInteger('guests')->default(0);
            $table->json('guest_names')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitees');
    }
};
