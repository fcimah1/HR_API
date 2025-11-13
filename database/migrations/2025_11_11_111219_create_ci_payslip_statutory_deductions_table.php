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
        Schema::create('ci_payslip_statutory_deductions', function (Blueprint $table) {
            $table->integer('payslip_deduction_id', true);
            $table->integer('payslip_id');
            $table->integer('staff_id');
            $table->integer('is_fixed');
            $table->string('pay_title', 200);
            $table->decimal('pay_amount', 65)->default(0);
            $table->string('salary_month', 200);
            $table->string('created_at', 200);
            $table->integer('contract_option_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_payslip_statutory_deductions');
    }
};
