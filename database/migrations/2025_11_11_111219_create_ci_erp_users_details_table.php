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
        Schema::create('ci_erp_users_details', function (Blueprint $table) {
            $table->integer('staff_details_id', true);
            $table->integer('company_id');
            $table->integer('user_id');
            $table->string('employee_id', 255);
            $table->integer('reporting_manager');
            $table->integer('department_id');
            $table->integer('designation_id');
            $table->integer('office_shift_id');
            $table->decimal('basic_salary', 65);
            $table->decimal('hourly_rate', 65);
            $table->integer('salay_type');
            $table->string('leave_categories', 255)->default('all');
            $table->mediumText('role_description')->nullable();
            $table->string('date_of_joining', 200)->nullable();
            $table->integer('contract_end')->default(0);
            $table->string('date_of_leaving', 200)->nullable();
            $table->string('date_of_birth', 200)->nullable();
            $table->integer('marital_status')->nullable();
            $table->integer('religion_id')->nullable();
            $table->string('blood_group', 200)->nullable();
            $table->integer('citizenship_id')->nullable();
            $table->mediumText('bio')->nullable();
            $table->integer('experience')->nullable();
            $table->mediumText('fb_profile')->nullable();
            $table->mediumText('twitter_profile')->nullable();
            $table->mediumText('gplus_profile')->nullable();
            $table->mediumText('linkedin_profile')->nullable();
            $table->string('account_title', 255)->nullable();
            $table->string('account_number', 255)->nullable();
            $table->integer('bank_name');
            $table->string('iban', 255)->nullable();
            $table->string('swift_code', 255)->nullable();
            $table->mediumText('bank_branch')->nullable();
            $table->string('default_language', 50)->nullable();
            $table->string('contact_full_name', 200)->nullable();
            $table->string('contact_phone_no', 200)->nullable();
            $table->string('contact_email', 200)->nullable();
            $table->mediumText('contact_address')->nullable();
            $table->integer('ml_tax_category');
            $table->string('ml_empployee_epf_rate', 100)->default('0');
            $table->string('ml_empployer_epf_rate', 100)->default('0');
            $table->integer('ml_eis_contribution');
            $table->integer('ml_socso_category');
            $table->string('ml_pcb_socso', 100)->default('2021');
            $table->integer('ml_hrdf');
            $table->integer('ml_tax_citizenship')->default(1);
            $table->decimal('zakat_fund', 65)->default(0);
            $table->integer('job_type');
            $table->string('assigned_hours', 100)->nullable();
            $table->text('leave_options')->nullable();
            $table->string('approval_levels', 255)->nullable();
            $table->string('approval_level01', 255)->nullable();
            $table->string('approval_level02', 255)->nullable();
            $table->string('approval_level03', 255)->nullable();
            $table->string('not_part_of_orgchart', 255)->nullable();
            $table->string('not_part_of_system_reports', 255)->nullable();
            $table->integer('is_accrual_pause')->default(0);
            $table->tinyInteger('is_work_from_home')->default(0);
            $table->tinyInteger('is_eqama')->default(1);
            $table->string('pause_start_date', 255)->nullable();
            $table->string('pause_start_end', 255)->nullable();
            $table->string('created_at', 200)->nullable();
            $table->string('employee_idnum', 155)->nullable();
            $table->integer('branch_id')->nullable();
            $table->string('contract_date_eqama', 155)->nullable();
            $table->string('salary_payment_method', 50)->nullable();
            $table->integer('currency_id')->nullable();
            $table->integer('contract_option_id')->nullable();
            $table->string('biotime_id', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_erp_users_details');
    }
};
