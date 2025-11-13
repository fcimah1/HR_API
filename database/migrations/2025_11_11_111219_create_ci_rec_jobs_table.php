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
        Schema::create('ci_rec_jobs', function (Blueprint $table) {
            $table->integer('job_id', true);
            $table->integer('company_id');
            $table->string('job_title', 255);
            $table->integer('designation_id');
            $table->integer('job_type');
            $table->integer('job_vacancy');
            $table->string('gender', 100);
            $table->string('minimum_experience', 255);
            $table->string('date_of_closing', 200);
            $table->mediumText('short_description');
            $table->mediumText('long_description');
            $table->integer('status');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_rec_jobs');
    }
};
