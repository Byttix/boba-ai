<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('total_price')->default(0);
            $table->integer('budget')->nullable();
            $table->enum('purpose', ['gaming', 'workstation', 'office', 'streaming', 'other'])->default('gaming');

            // ID компонентов из SQLite базы
            $table->integer('cpu_id')->nullable();
            $table->integer('motherboard_id')->nullable();
            $table->integer('ram_id')->nullable();
            $table->integer('ram_quantity')->default(1);
            $table->integer('gpu_id')->nullable();
            $table->integer('power_supply_id')->nullable();
            $table->integer('cpu_cooler_id')->nullable();
            $table->integer('case_id')->nullable();
            $table->integer('storage_id')->nullable();

            $table->timestamps();

            // Индексы для быстрого поиска
            $table->index('user_id');
            $table->index('total_price');
            $table->index('purpose');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builds');
    }
};
