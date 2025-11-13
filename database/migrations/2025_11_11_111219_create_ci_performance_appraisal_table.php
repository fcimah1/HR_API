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
        Schema::create('ci_performance_appraisal', function (Blueprint $table) {
            $table->integer('performance_appraisal_id', true);
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->string('title', 200)->nullable();
            $table->string('appraisal_year_month', 255);
            $table->mediumText('remarks');
            $table->integer('added_by');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_performance_appraisal');
    }
};
