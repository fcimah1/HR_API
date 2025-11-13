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
        Schema::create('ci_forms', function (Blueprint $table) {
            $table->integer('forms_id', true);
            $table->integer('company_id');
            $table->integer('category_id');
            $table->integer('supervisor_id');
            $table->string('form_name', 255)->nullable();
            $table->tinyInteger('is_allow_attachment')->default(0);
            $table->tinyInteger('is_attachment_mandatory')->default(0);
            $table->tinyInteger('is_allow_fill')->default(0);
            $table->text('json_data')->nullable();
            $table->text('assigned_to')->nullable();
            $table->integer('status');
            $table->string('notify_upon_submission', 255)->nullable();
            $table->string('notify_upon_approval', 255)->nullable();
            $table->string('notify_upon_rejection', 255)->nullable();
            $table->string('approval_method', 255)->nullable();
            $table->string('approval_levels', 255)->nullable();
            $table->string('approval_level01', 155)->nullable();
            $table->integer('approval_level02')->nullable();
            $table->integer('approval_level03')->nullable();
            $table->integer('approval_level04')->nullable();
            $table->integer('approval_level05')->nullable();
            $table->string('skip_specific_approval', 155)->nullable();
            $table->text('assign_departments')->nullable();
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_forms');
    }
};
