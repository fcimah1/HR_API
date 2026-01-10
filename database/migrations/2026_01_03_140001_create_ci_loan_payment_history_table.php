<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ci_loan_payment_history', function (Blueprint $table) {
            $table->integer('payment_id', true);
            $table->integer('advance_salary_id');
            $table->integer('employee_id');
            $table->integer('company_id');
            $table->decimal('amount_due', 65, 2);
            $table->decimal('amount_paid', 65, 2)->default(0);
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->boolean('is_late')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employee_id', 'is_late'], 'idx_employee_late_payments');
            $table->index('advance_salary_id', 'idx_advance_salary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_loan_payment_history');
    }
};
