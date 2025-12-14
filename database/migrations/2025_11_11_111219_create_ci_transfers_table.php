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
        Schema::create('ci_transfers', function (Blueprint $table) {
            $table->integer('transfer_id', true);
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->integer('old_salary')->nullable();
            $table->integer('old_designation')->nullable();
            $table->integer('old_department')->nullable();
            $table->string('transfer_date', 255);
            $table->integer('transfer_department')->nullable();
            $table->integer('transfer_designation')->nullable();
            $table->integer('new_salary')->nullable();
            $table->integer('old_company_id')->nullable()->index('idx_old_company')->comment('الشركة القديمة');
            $table->integer('old_branch_id')->nullable();
            $table->integer('new_company_id')->nullable()->index('idx_new_company')->comment('الشركة الجديدة');
            $table->integer('new_branch_id')->nullable();
            $table->integer('old_currency')->nullable()->comment('العملة القديمة');
            $table->integer('new_currency')->nullable()->comment('العملة الجديدة');
            $table->mediumText('reason');
            $table->tinyInteger('status');
            $table->tinyInteger('current_company_approval')->nullable()->comment('0=pending, 1=approved, 2=rejected, NULL=not applicable (internal transfer)');
            $table->tinyInteger('new_company_approval')->nullable()->comment('0=pending, 1=approved, 2=rejected, NULL=not applicable (internal transfer)');
            $table->enum('transfer_type', ['internal', 'branch', 'intercompany'])->default('internal')->comment('Type of transfer');
            $table->integer('added_by');
            $table->text('notify_send_to')->nullable();
            $table->string('created_at', 255);

            // Index for pending approvals
            $table->index(['new_company_id', 'new_company_approval'], 'idx_new_company_pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_transfers');
    }
};
