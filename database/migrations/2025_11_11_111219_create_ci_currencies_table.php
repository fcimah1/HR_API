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
        Schema::create('ci_currencies', function (Blueprint $table) {
            $table->integer('currency_id', true);
            $table->string('country_name', 150);
            $table->string('currency_name', 20);
            $table->string('currency_code', 20);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_currencies');
    }
};
