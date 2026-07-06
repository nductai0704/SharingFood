<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('food_claims', function (Blueprint $table) {
            // 1. Nhóm trường Phương thức nhận hàng
            $table->string('shipping_method')->default('self_pickup'); // self_pickup, relative_pickup, delivery_service
            $table->string('pickup_contact_name')->nullable();
            $table->string('pickup_contact_phone')->nullable();
            $table->string('driver_license_plate')->nullable();
            $table->string('delivery_service_company')->nullable();

            // 2. Nhóm trường Phục vụ Countdown Timer (Thời gian duyệt/hết hạn)
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // 3. Nhóm trường Hủy đơn
            $table->string('cancel_reason')->nullable();
            $table->string('cancelled_by')->nullable(); // user_id hoặc 'system' nếu quá hạn
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('food_claims', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_method',
                'pickup_contact_name',
                'pickup_contact_phone',
                'driver_license_plate',
                'delivery_service_company',
                'approved_at',
                'expires_at',
                'cancel_reason',
                'cancelled_by'
            ]);
        });
    }
};
