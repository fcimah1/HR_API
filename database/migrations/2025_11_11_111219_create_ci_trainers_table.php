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
        Schema::create('ci_trainers', function (Blueprint $table) {
            $table->integer('trainer_id', true);
            $table->integer('company_id');
            $table->string('first_name', 255);
            $table->string('last_name', 255);
            $table->string('contact_number', 255);
            $table->string('email', 255);
            $table->mediumText('expertise');
            $table->mediumText('address');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_trainers');
    }
};
