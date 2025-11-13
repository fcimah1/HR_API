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
        Schema::create('ci_module_attributes', function (Blueprint $table) {
            $table->integer('custom_field_id', true);
            $table->integer('company_id');
            $table->integer('module_id');
            $table->string('attribute', 255);
            $table->string('attribute_label', 255);
            $table->string('attribute_type', 255);
            $table->string('col_width', 100)->nullable();
            $table->integer('validation');
            $table->integer('priority');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_module_attributes');
    }
};
