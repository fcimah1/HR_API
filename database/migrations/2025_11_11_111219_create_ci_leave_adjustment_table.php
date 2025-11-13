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
        Schema::create('ci_leave_adjustment', function (Blueprint $table) {
            $table->integer('adjustment_id', true);
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->integer('duty_employee_id')->nullable();
            $table->integer('leave_type_id');
            $table->string('adjust_hours', 200)->default('0');
            $table->text('reason_adjustment');
            $table->integer('status');
            $table->string('created_at', 200);
            $table->date('adjustment_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_leave_adjustment');
    }
};
