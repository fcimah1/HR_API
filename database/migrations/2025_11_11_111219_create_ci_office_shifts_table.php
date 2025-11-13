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
        Schema::create('ci_office_shifts', function (Blueprint $table) {
            $table->integer('office_shift_id', true);
            $table->integer('company_id');
            $table->string('shift_name', 255);
            $table->string('monday_in_time', 222);
            $table->string('monday_out_time', 222);
            $table->string('tuesday_in_time', 222);
            $table->string('tuesday_out_time', 222);
            $table->string('wednesday_in_time', 222);
            $table->string('wednesday_out_time', 222);
            $table->string('thursday_in_time', 222);
            $table->string('thursday_out_time', 222);
            $table->string('friday_in_time', 222);
            $table->string('friday_out_time', 222);
            $table->string('saturday_in_time', 222);
            $table->string('saturday_out_time', 222);
            $table->string('sunday_in_time', 222);
            $table->string('sunday_out_time', 222);
            $table->string('monday_lunch_break', 100)->nullable();
            $table->string('tuesday_lunch_break', 100)->nullable();
            $table->string('wednesday_lunch_break', 100)->nullable();
            $table->string('thursday_lunch_break', 100)->nullable();
            $table->string('friday_lunch_break', 100)->nullable();
            $table->string('saturday_lunch_break', 100)->nullable();
            $table->string('sunday_lunch_break', 100)->nullable();
            $table->string('monday_lunch_break_out', 100)->nullable();
            $table->string('tuesday_lunch_break_out', 100)->nullable();
            $table->string('wednesday_lunch_break_out', 100)->nullable();
            $table->string('thursday_lunch_break_out', 100)->nullable();
            $table->string('friday_lunch_break_out', 100)->nullable();
            $table->string('saturday_lunch_break_out', 100)->nullable();
            $table->string('sunday_lunch_break_out', 100)->nullable();
            $table->string('created_at', 222);
            $table->integer('hours_per_day');
            $table->dateTime('in_time_beginning')->nullable();
            $table->time('in_time_end')->nullable();
            $table->integer('late_allowance')->nullable();
            $table->time('out_time_beginning')->nullable();
            $table->time('out_time_end')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_office_shifts');
    }
};
