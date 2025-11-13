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
        Schema::create('ci_erp_end_of_service_calculations', function (Blueprint $table) {
            $table->integer('calculation_id', true);
            $table->integer('company_id')->index('idx_company_id');
            $table->integer('employee_id')->index('idx_employee_id');
            $table->date('hire_date');
            $table->date('termination_date')->index('idx_termination_date');
            $table->string('termination_type', 50);
            $table->integer('service_years')->default(0);
            $table->integer('service_months')->default(0);
            $table->integer('service_days')->default(0);
            $table->decimal('basic_salary', 15)->default(0);
            $table->decimal('allowances', 15)->default(0);
            $table->decimal('total_salary', 15)->default(0);
            $table->decimal('gratuity_amount', 15)->default(0);
            $table->decimal('leave_compensation', 15)->default(0);
            $table->decimal('notice_compensation', 15)->default(0);
            $table->decimal('total_compensation', 15)->default(0);
            $table->integer('unused_leave_days')->default(0);
            $table->integer('calculated_by')->index('idx_calculated_by');
            $table->dateTime('calculated_at');
            $table->text('notes')->nullable();
            $table->boolean('is_approved')->default(false)->index('idx_is_approved');
            $table->integer('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrentOnUpdate()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_erp_end_of_service_calculations');
    }
};
