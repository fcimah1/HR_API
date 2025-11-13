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
        Schema::create('ci_promotions', function (Blueprint $table) {
            $table->integer('promotion_id', true);
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->string('promotion_title', 255);
            $table->date('promotion_date');
            $table->text('description')->nullable();
            $table->boolean('status')->default(false)->comment('0 = Pending, 1 = Approved, 2 = Rejected');
            $table->integer('new_designation_id');
            $table->integer('new_department_id');
            $table->decimal('new_salary', 10);
            $table->integer('old_designation_id');
            $table->integer('old_department_id');
            $table->decimal('old_salary', 10);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_promotions');
    }
};
