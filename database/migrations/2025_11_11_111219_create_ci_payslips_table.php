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
        Schema::create('ci_payslips', function (Blueprint $table) {
            $table->integer('payslip_id', true);
            $table->string('payslip_key', 200);
            $table->integer('company_id');
            $table->integer('staff_id');
            $table->integer('contract_option_id')->nullable();
            $table->string('salary_month', 200);
            $table->integer('wages_type');
            $table->string('payslip_type', 50);
            $table->decimal('basic_salary', 65)->default(0);
            $table->decimal('daily_wages', 65)->default(0);
            $table->string('hours_worked', 50)->default('0');
            $table->decimal('total_allowances', 65)->default(0);
            $table->decimal('total_commissions', 65)->default(0);
            $table->decimal('total_statutory_deductions', 65)->default(0);
            $table->decimal('total_other_payments', 65)->default(0);
            $table->decimal('net_salary', 65)->default(0);
            $table->integer('payment_method');
            $table->mediumText('pay_comments');
            $table->integer('is_payment');
            $table->string('year_to_date', 200);
            $table->integer('is_advance_salary_deduct');
            $table->decimal('advance_salary_amount', 65)->nullable();
            $table->integer('is_loan_deduct');
            $table->decimal('loan_amount', 65);
            $table->decimal('unpaid_leave_days', 10)->default(0);
            $table->decimal('unpaid_leave_deduction', 65)->default(0);
            $table->decimal('eis_value', 65)->default(0);
            $table->decimal('employer_eis_value', 65)->default(0);
            $table->decimal('epf_value', 65)->default(0);
            $table->decimal('employer_epf_value', 65)->default(0);
            $table->decimal('socso_value', 65)->default(0);
            $table->decimal('employer_socso_value', 65)->default(0);
            $table->decimal('pcb_tax_value', 65)->default(0);
            $table->decimal('ihrdf_value', 65)->default(0);
            $table->integer('status');
            $table->string('created_at', 200);
            $table->string('salary_payment_method', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_payslips');
    }
};
