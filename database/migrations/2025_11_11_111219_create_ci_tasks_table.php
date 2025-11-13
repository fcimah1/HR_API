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
        Schema::create('ci_tasks', function (Blueprint $table) {
            $table->integer('task_id', true);
            $table->integer('company_id');
            $table->integer('project_id');
            $table->string('task_type', 200)->default('project');
            $table->string('task_name', 255);
            $table->string('assigned_to', 255)->nullable();
            $table->text('associated_goals')->nullable();
            $table->string('start_date', 200);
            $table->string('end_date', 200);
            $table->string('task_hour', 200)->nullable();
            $table->string('task_progress', 200)->nullable();
            $table->text('summary');
            $table->mediumText('description')->nullable();
            $table->integer('task_status');
            $table->mediumText('task_note')->nullable();
            $table->integer('created_by');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_tasks');
    }
};
