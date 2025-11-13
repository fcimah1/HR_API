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
        Schema::create('ci_holidays', function (Blueprint $table) {
            $table->integer('holiday_id', true);
            $table->integer('company_id');
            $table->string('event_name', 200);
            $table->mediumText('description');
            $table->string('start_date', 200);
            $table->string('end_date', 200);
            $table->boolean('is_publish');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_holidays');
    }
};
