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
        Schema::create('ci_tasks_notes', function (Blueprint $table) {
            $table->integer('task_note_id', true);
            $table->integer('company_id');
            $table->integer('task_id');
            $table->integer('employee_id');
            $table->text('task_note')->nullable();
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_tasks_notes');
    }
};
