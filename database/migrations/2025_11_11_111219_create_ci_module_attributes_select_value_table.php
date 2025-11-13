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
        Schema::create('ci_module_attributes_select_value', function (Blueprint $table) {
            $table->integer('attributes_select_value_id', true);
            $table->integer('company_id');
            $table->integer('custom_field_id');
            $table->string('select_label', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_module_attributes_select_value');
    }
};
