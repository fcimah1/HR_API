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
        Schema::create('ci_track_goals', function (Blueprint $table) {
            $table->integer('tracking_id', true);
            $table->integer('company_id');
            $table->integer('tracking_type_id');
            $table->string('start_date', 200);
            $table->string('end_date', 200);
            $table->string('subject', 255);
            $table->string('target_achiement', 255);
            $table->mediumText('description')->nullable();
            $table->text('goal_work')->nullable();
            $table->string('goal_progress', 200)->nullable();
            $table->integer('goal_status')->default(0);
            $table->integer('goal_rating');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_track_goals');
    }
};
