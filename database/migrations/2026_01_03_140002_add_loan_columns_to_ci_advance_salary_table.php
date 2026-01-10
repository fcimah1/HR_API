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
        Schema::table('ci_advance_salary', function (Blueprint $table) {
            $table->integer('loan_tier_id')->nullable()->after('salary_type');
            $table->decimal('employee_salary', 65, 2)->nullable()->after('advance_amount');
            $table->integer('guarantor_id')->nullable()->after('is_deducted_from_salary');
            $table->integer('requested_months')->nullable()->after('monthly_installment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ci_advance_salary', function (Blueprint $table) {
            $table->dropColumn(['loan_tier_id', 'employee_salary', 'guarantor_id', 'requested_months']);
        });
    }
};
