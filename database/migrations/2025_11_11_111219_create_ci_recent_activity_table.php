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
        Schema::create('ci_recent_activity', function (Blueprint $table) {
            $table->integer('activity_id', true);
            $table->integer('company_id');
            $table->integer('staff_id');
            $table->integer('module_id');
            $table->string('module_type', 200);
            $table->integer('is_read')->default(0);
            $table->integer('added_by');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_recent_activity');
    }
};
