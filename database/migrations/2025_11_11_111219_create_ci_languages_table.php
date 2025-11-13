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
        Schema::create('ci_languages', function (Blueprint $table) {
            $table->integer('language_id', true);
            $table->string('language_name', 255);
            $table->string('language_code', 255);
            $table->string('language_flag', 255);
            $table->integer('is_active');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_languages');
    }
};
