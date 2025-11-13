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
        Schema::create('ci_rec_interviews', function (Blueprint $table) {
            $table->integer('job_interview_id', true);
            $table->integer('company_id');
            $table->integer('job_id');
            $table->integer('designation_id');
            $table->string('staff_id', 11);
            $table->string('interview_place', 255);
            $table->string('interview_date', 255);
            $table->string('interview_time', 255);
            $table->integer('interviewer_id');
            $table->mediumText('description');
            $table->text('interview_remarks')->nullable();
            $table->integer('status');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_rec_interviews');
    }
};
