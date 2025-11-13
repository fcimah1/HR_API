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
        Schema::create('ci_finance_entity', function (Blueprint $table) {
            $table->integer('entity_id', true);
            $table->integer('company_id');
            $table->string('name', 100);
            $table->string('contact_number', 100);
            $table->string('type', 15);
            $table->string('created_at', 100);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_finance_entity');
    }
};
