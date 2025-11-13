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
        Schema::create('ci_module_attributes_values', function (Blueprint $table) {
            $table->integer('attributes_value_id', true);
            $table->integer('company_id');
            $table->integer('user_id');
            $table->integer('module_attributes_id');
            $table->text('attribute_value')->nullable();
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_module_attributes_values');
    }
};
