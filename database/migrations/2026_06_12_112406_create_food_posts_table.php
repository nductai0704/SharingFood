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
        // Sử dụng Schema::table thay vì Schema::create để bổ sung cột vào bảng đã có
        Schema::table('food_posts', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('expires_at');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('food_posts', function (Blueprint $table) {
            // Xóa 2 cột đã thêm nếu rollback
            $table->dropColumn(['latitude', 'longitude']);
        });
    }

};
