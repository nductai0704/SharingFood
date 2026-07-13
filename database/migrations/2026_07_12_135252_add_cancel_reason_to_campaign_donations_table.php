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
            $table->string('cancel_reason')->nullable()->after('status');
            $table->string('cancelled_by')->nullable()->after('cancel_reason'); // user_id (charity) hoặc 'system'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_donations', function (Blueprint $table) {
            $table->dropColumn(['cancel_reason', 'cancelled_by']);
        });
    }
};
