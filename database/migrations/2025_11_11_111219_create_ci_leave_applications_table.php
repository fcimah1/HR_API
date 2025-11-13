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
        Schema::create('ci_leave_applications', function (Blueprint $table) {
            $table->integer('leave_id', true);
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->integer('duty_employee_id')->nullable();
            $table->integer('leave_type_id');
            $table->string('from_date', 200);
            $table->string('to_date', 200);
            $table->string('leave_hours', 100)->nullable();
            $table->string('particular_date', 100)->nullable();
            $table->string('leave_month', 50)->nullable();
            $table->string('leave_year', 100)->nullable();
            $table->mediumText('reason');
            $table->mediumText('remarks')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('is_half_day')->nullable();
            $table->string('leave_attachment', 255)->nullable();
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_leave_applications');
    }
};
