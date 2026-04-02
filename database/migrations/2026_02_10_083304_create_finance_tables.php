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
        // 1. Finance Accounts Table (Company Accounts)
        Schema::create('ci_finance_accounts', function (Blueprint $table) {
            $table->id('account_id');
            $table->integer('company_id');
            $table->string('account_name', 255);
            $table->decimal('account_balance', 65, 2)->default(0.00);
            $table->decimal('account_opening_balance', 65, 2)->default(0.00);
            $table->string('account_number', 255)->nullable();
            $table->string('branch_code', 255)->nullable();
            $table->text('bank_branch')->nullable();
            $table->timestamps();
        });

        // 2. Employee Accounts Table
        Schema::create('ci_employee_accounts', function (Blueprint $table) {
            $table->id('account_id');
            $table->integer('company_id');
            $table->string('account_name', 200); // e.g., Employee Name or specific account name
            $table->string('created_at', 200)->nullable(); // As per image type
            // Adding updated_at for standard Laravel behavior, though image didn't show it explicitly
            $table->timestamp('updated_at')->nullable();
        });

        // NOTE: Finance Categories are stored in `ci_erp_constants`.
        // 3. Finance Transactions Table
        Schema::create('ci_finance_transactions', function (Blueprint $table) {
            $table->id('transaction_id');
            $table->integer('account_id'); // FK to ci_finance_accounts
            $table->integer('company_id');
            $table->integer('staff_id'); // User who performed the action
            $table->date('transaction_date');
            $table->string('transaction_type', 100); // e.g., 'deposit', 'expense', 'transfer'

            // Polymorphic relation for related entity (e.g., Project, Invoice)
            $table->integer('entity_id')->default(0);
            $table->string('entity_type', 100)->nullable();

            $table->integer('entity_category_id')->default(0); // FK to ci_erp_constants.constants_id
            $table->mediumText('description')->nullable();
            $table->decimal('amount', 65, 2)->default(0.00);
            $table->enum('dr_cr', ['dr', 'cr']); // Debit or Credit
            $table->integer('payment_method_id')->default(0);
            $table->string('reference', 100)->nullable();
            $table->string('attachment_file', 100)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_finance_transactions');
        Schema::dropIfExists('ci_finance_accounts');
    }
};
