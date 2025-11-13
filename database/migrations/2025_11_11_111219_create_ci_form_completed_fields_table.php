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
        Schema::create('ci_form_completed_fields', function (Blueprint $table) {
            $table->integer('completed_field_id', true);
            $table->integer('company_id');
            $table->integer('form_id');
            $table->integer('staff_id');
            $table->string('field_key', 255)->nullable();
            $table->text('field_value');
            $table->integer('status');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_form_completed_fields');
    }
};
