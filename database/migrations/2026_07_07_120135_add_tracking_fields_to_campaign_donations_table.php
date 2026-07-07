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
        Schema::table('campaign_donations', function (Blueprint $table) {
            $table->string('donation_code', 20)->nullable()->index()->after('id');
            $table->string('shipper_name')->nullable()->after('shipping_method');
            $table->string('shipper_license_plate')->nullable()->after('shipper_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_donations', function (Blueprint $table) {
            $table->dropColumn(['donation_code', 'shipper_name', 'shipper_license_plate']);
        });
    }
};
