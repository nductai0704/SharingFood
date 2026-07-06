<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
       /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('food_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('original_quantity');
            $table->integer('remain_quantity');
            $table->string('unit');
            $table->string('image_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('available');
            $table->string('ai_status')->default('safe');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_posts');
    }

};
