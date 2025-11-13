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
        Schema::create('ci_events', function (Blueprint $table) {
            $table->integer('event_id', true);
            $table->integer('company_id');
            $table->string('employee_id', 255)->nullable();
            $table->string('event_title', 255);
            $table->string('event_date', 255);
            $table->string('event_time', 255);
            $table->mediumText('event_note');
            $table->string('event_color', 200);
            $table->integer('is_show_calendar');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_events');
    }
};
