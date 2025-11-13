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
        Schema::create('ci_performance_indicator', function (Blueprint $table) {
            $table->integer('performance_indicator_id', true);
            $table->integer('company_id');
            $table->string('title', 255)->nullable();
            $table->integer('designation_id');
            $table->integer('added_by');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_performance_indicator');
    }
};
