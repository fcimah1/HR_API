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
        Schema::create('ci_timesheet_request', function (Blueprint $table) {
            $table->integer('time_request_id', true);
            $table->integer('company_id');
            $table->integer('staff_id');
            $table->string('request_date', 255);
            $table->string('request_month', 255);
            $table->string('clock_in', 200);
            $table->string('clock_out', 200);
            $table->string('total_hours', 255);
            $table->mediumText('request_reason');
            $table->boolean('is_approved');
            $table->integer('overtime_reason');
            $table->integer('additional_work_hours');
            $table->string('straight', 200)->nullable();
            $table->string('time_a_half', 200)->nullable();
            $table->string('double_overtime', 200)->nullable();
            $table->integer('compensation_type');
            $table->string('compensation_banked', 255)->nullable();
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_timesheet_request');
    }
};
