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
        Schema::create('ci_erp_notifications_approval', function (Blueprint $table) {
            $table->integer('staff_approval_id', true);
            $table->integer('company_id');
            $table->integer('staff_id');
            $table->string('module_option', 255)->nullable();
            $table->string('module_key_id', 255)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->string('approval_level', 255)->nullable();
            $table->string('updated_at', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_erp_notifications_approval');
    }
};
