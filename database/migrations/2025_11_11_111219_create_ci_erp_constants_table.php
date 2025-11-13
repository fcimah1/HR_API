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
        Schema::create('ci_erp_constants', function (Blueprint $table) {
            $table->integer('constants_id', true);
            $table->integer('company_id');
            $table->string('type', 100);
            $table->string('category_name', 200);
            $table->text('field_one')->nullable();
            $table->text('field_two')->nullable();
            $table->string('field_three', 200)->nullable();
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_erp_constants');
    }
};
