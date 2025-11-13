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
        Schema::create('ci_erp_notifications', function (Blueprint $table) {
            $table->integer('notification_id', true);
            $table->integer('company_id');
            $table->text('module_options')->nullable();
            $table->string('notify_upon_submission', 255)->nullable();
            $table->string('notify_upon_approval', 255)->nullable();
            $table->string('approval_method', 255)->nullable();
            $table->string('approval_level', 255)->nullable();
            $table->string('approval_level01', 255)->nullable();
            $table->string('approval_level02', 255)->nullable();
            $table->string('approval_level03', 255)->nullable();
            $table->string('approval_level04', 255)->nullable();
            $table->string('approval_level05', 255)->nullable();
            $table->string('skip_specific_approval', 255)->nullable();
            $table->string('added_at', 255)->nullable();
            $table->string('updated_at', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_erp_notifications');
    }
};
