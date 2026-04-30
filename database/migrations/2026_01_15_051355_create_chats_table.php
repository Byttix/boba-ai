<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('last_message')->nullable();
            $table->integer('budget')->nullable();
            $table->enum('purpose', ['gaming', 'workstation', 'office', 'streaming', 'other'])->default('gaming');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
