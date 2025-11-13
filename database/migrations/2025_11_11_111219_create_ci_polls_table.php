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
        Schema::create('ci_polls', function (Blueprint $table) {
            $table->integer('poll_id', true);
            $table->integer('company_id');
            $table->string('poll_title', 255)->nullable();
            $table->string('poll_question', 255)->nullable();
            $table->string('poll_start_date', 200)->nullable();
            $table->string('poll_end_date', 200)->nullable();
            $table->string('poll_answer1', 255)->nullable();
            $table->string('poll_answer2', 255)->nullable();
            $table->string('poll_answer3', 255)->nullable();
            $table->string('poll_answer4', 255)->nullable();
            $table->string('poll_answer5', 255)->nullable();
            $table->text('notes')->nullable();
            $table->integer('added_by');
            $table->integer('is_active');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_polls');
    }
};
