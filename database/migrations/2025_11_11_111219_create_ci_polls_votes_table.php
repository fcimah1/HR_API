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
        Schema::create('ci_polls_votes', function (Blueprint $table) {
            $table->integer('polls_vote_id', true);
            $table->integer('company_id');
            $table->integer('poll_id');
            $table->string('poll_question_id', 255)->nullable();
            $table->string('poll_answer', 255);
            $table->integer('user_id');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_polls_votes');
    }
};
