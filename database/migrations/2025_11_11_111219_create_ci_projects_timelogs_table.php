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
        Schema::create('ci_projects_timelogs', function (Blueprint $table) {
            $table->integer('timelogs_id', true);
            $table->integer('project_id');
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->string('start_time', 255);
            $table->string('end_time', 255);
            $table->string('start_date', 255);
            $table->string('end_date', 255);
            $table->string('total_hours', 255);
            $table->text('timelogs_memo');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_projects_timelogs');
    }
};
