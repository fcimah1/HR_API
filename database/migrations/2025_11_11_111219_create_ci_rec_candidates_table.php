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
        Schema::create('ci_rec_candidates', function (Blueprint $table) {
            $table->integer('candidate_id', true);
            $table->integer('company_id');
            $table->integer('job_id');
            $table->integer('designation_id');
            $table->integer('staff_id');
            $table->mediumText('message');
            $table->mediumText('job_resume');
            $table->integer('application_status')->default(0);
            $table->mediumText('application_remarks');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_rec_candidates');
    }
};
