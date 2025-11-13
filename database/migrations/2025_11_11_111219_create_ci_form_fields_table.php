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
        Schema::create('ci_form_fields', function (Blueprint $table) {
            $table->integer('form_field_id', true);
            $table->integer('company_id');
            $table->integer('form_id');
            $table->string('field_type', 200);
            $table->string('field_key', 200);
            $table->string('field_label', 200)->nullable();
            $table->integer('sort_order');
            $table->text('json_data');
            $table->integer('is_mandatory')->default(0);
            $table->integer('status');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_form_fields');
    }
};
