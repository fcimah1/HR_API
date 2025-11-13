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
        Schema::create('ci_advance_salary', function (Blueprint $table) {
            $table->integer('advance_salary_id', true);
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->string('salary_type', 100)->nullable();
            $table->string('month_year', 255);
            $table->decimal('advance_amount', 65);
            $table->string('one_time_deduct', 50);
            $table->decimal('monthly_installment', 65);
            $table->decimal('total_paid', 65);
            $table->text('reason');
            $table->integer('status')->nullable();
            $table->integer('is_deducted_from_salary')->nullable();
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_advance_salary');
    }
};
