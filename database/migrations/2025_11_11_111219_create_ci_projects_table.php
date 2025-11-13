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
        Schema::create('ci_projects', function (Blueprint $table) {
            $table->integer('project_id', true);
            $table->integer('company_id');
            $table->integer('client_id');
            $table->string('title', 255);
            $table->string('start_date', 255);
            $table->string('end_date', 255);
            $table->mediumText('assigned_to')->nullable();
            $table->text('associated_goals')->nullable();
            $table->string('priority', 255);
            $table->string('project_no', 255)->nullable();
            $table->string('budget_hours', 255)->nullable();
            $table->mediumText('summary');
            $table->mediumText('description')->nullable();
            $table->string('project_progress', 255);
            $table->longText('project_note')->nullable();
            $table->tinyInteger('status');
            $table->integer('added_by');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_projects');
    }
};
