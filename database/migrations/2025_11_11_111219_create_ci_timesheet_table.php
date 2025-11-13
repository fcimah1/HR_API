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
        Schema::create('ci_timesheet', function (Blueprint $table) {
            $table->integer('time_attendance_id', true);
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->string('attendance_date', 255);
            $table->string('clock_in', 255);
            $table->string('clock_in_ip_address', 255);
            $table->string('clock_out', 255);
            $table->string('clock_out_ip_address', 255);
            $table->string('clock_in_out', 255);
            $table->string('clock_in_latitude', 150);
            $table->string('clock_in_longitude', 150);
            $table->string('clock_out_latitude', 150);
            $table->string('clock_out_longitude', 150);
            $table->string('time_late', 255);
            $table->string('early_leaving', 255);
            $table->string('overtime', 255);
            $table->string('total_work', 255);
            $table->string('total_rest', 255);
            $table->integer('shift_id');
            $table->integer('work_from_home');
            $table->string('lunch_breakin', 200)->nullable();
            $table->string('lunch_breakout', 200)->nullable();
            $table->string('attendance_status', 100);
            $table->string('status', 255)->nullable()->default('Pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_timesheet');
    }
};
