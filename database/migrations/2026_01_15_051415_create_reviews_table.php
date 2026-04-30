<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('build_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->integer('rating')->default(5);
            $table->text('comment');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
