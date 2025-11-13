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
        Schema::create('ci_cms_pages', function (Blueprint $table) {
            $table->integer('page_id', true);
            $table->text('page_name')->nullable();
            $table->string('page_type', 100);
            $table->text('page_description')->nullable();
            $table->string('lang', 55)->nullable();
            $table->integer('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_cms_pages');
    }
};
