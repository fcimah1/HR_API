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
        Schema::create('ci_polls_questions', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('poll_ref_id', 255);
            $table->integer('company_id');
            $table->string('poll_question', 255)->nullable();
            $table->string('poll_answer1', 255)->nullable();
            $table->string('poll_answer2', 255)->nullable();
            $table->string('poll_answer3', 255)->nullable();
            $table->string('poll_answer4', 255)->nullable();
            $table->string('poll_answer5', 255)->nullable();
            $table->text('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_polls_questions');
    }
};
