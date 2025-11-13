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
        Schema::create('ci_performance_appraisal_options', function (Blueprint $table) {
            $table->integer('performance_appraisal_options_id', true);
            $table->integer('company_id');
            $table->integer('appraisal_id');
            $table->string('appraisal_type', 200);
            $table->integer('appraisal_option_id');
            $table->integer('appraisal_option_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_performance_appraisal_options');
    }
};
