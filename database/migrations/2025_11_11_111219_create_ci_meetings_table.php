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
        Schema::create('ci_meetings', function (Blueprint $table) {
            $table->integer('meeting_id', true);
            $table->integer('company_id');
            $table->string('employee_id', 255)->nullable();
            $table->string('meeting_title', 255);
            $table->string('meeting_date', 255);
            $table->string('meeting_time', 255);
            $table->string('meeting_room', 255);
            $table->mediumText('meeting_note');
            $table->string('meeting_color', 200);
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_meetings');
    }
};
