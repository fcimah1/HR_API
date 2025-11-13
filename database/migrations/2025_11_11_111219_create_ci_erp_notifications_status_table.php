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
        Schema::create('ci_erp_notifications_status', function (Blueprint $table) {
            $table->integer('notification_status_id', true);
            $table->text('module_option');
            $table->string('module_status', 255)->nullable();
            $table->string('module_key_id', 255);
            $table->integer('staff_id')->nullable();
            $table->integer('is_read')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_erp_notifications_status');
    }
};
