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
        // Check if table exists to prevent errors if migration is re-run or partially applied
        if (!Schema::hasTable('ci_policy_results')) {
            Schema::create('ci_policy_results', function (Blueprint $table) {
                // `result_id` int NOT NULL AUTO_INCREMENT PRIMARY KEY
                $table->id('result_id');

                // `company_id` int NOT NULL
                $table->integer('company_id');

                // `policy_id` int NOT NULL DEFAULT '1' COMMENT '1=Travel, 2=Transport, 3=Training'
                $table->integer('policy_id')->default(1)->comment('1=Travel, 2=Transport, 3=Training');

                // `employee_id` int DEFAULT NULL
                $table->integer('employee_id')->nullable();

                // `travel_id` int DEFAULT NULL
                $table->integer('travel_id')->nullable();

                // `config_id` int NOT NULL
                $table->integer('config_id');

                // `hierarchy_level` int DEFAULT NULL
                $table->integer('hierarchy_level')->nullable();

                // `start_date` date NOT NULL
                $table->date('start_date');

                // `end_date` date NOT NULL
                $table->date('end_date');

                // `total_days` int NOT NULL
                $table->integer('total_days');

                // `daily_rate` decimal(10,2) NOT NULL
                $table->decimal('daily_rate', 10, 2);

                // `total_amount` decimal(10,2) NOT NULL
                $table->decimal('total_amount', 10, 2);

                // `currency_base` varchar(10) NOT NULL
                $table->string('currency_base', 10);

                // `currency_local` varchar(10) NOT NULL
                $table->string('currency_local', 10);

                // `weekly_breakdown` json DEFAULT NULL
                $table->json('weekly_breakdown')->nullable();

                // `component_breakdown` json DEFAULT NULL
                $table->json('component_breakdown')->nullable();

                // `status` tinyint NOT NULL DEFAULT '0'
                $table->tinyInteger('status')->default(0);

                // `payslip_id` int DEFAULT NULL
                $table->integer('payslip_id')->nullable();

                // `calculated_by` int NOT NULL
                $table->integer('calculated_by');

                // `created_at` datetime NOT NULL
                // `approved_at` datetime DEFAULT NULL
                // Using standard timestamps for created_at/updated_at, but schema asks specifically for created_at and approved_at.
                // We will create them manually to match the exact requested schema.
                $table->dateTime('created_at');
                $table->dateTime('approved_at')->nullable();

                // `approved_by` int DEFAULT NULL
                $table->integer('approved_by')->nullable();

                // Optimizations (Indexes) - Good practice for The Backend Architect
                $table->index('company_id');
                $table->index('employee_id');
                $table->index('policy_id');
                $table->index('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_policy_results');
    }
};
