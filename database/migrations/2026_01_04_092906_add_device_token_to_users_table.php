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
        Schema::table('ci_erp_users', function (Blueprint $table) {
            if (!Schema::hasColumn('ci_erp_users', 'device_token')) {
                $table->text('device_token')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ci_erp_users', function (Blueprint $table) {
            if (Schema::hasColumn('ci_erp_users', 'device_token')) {
                $table->dropColumn('device_token');
            }
        });
    }
};
