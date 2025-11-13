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
        Schema::create('ci_complaints', function (Blueprint $table) {
            $table->integer('complaint_id', true);
            $table->integer('company_id');
            $table->integer('complaint_from');
            $table->string('title', 255);
            $table->string('complaint_date', 255);
            $table->mediumText('complaint_against');
            $table->mediumText('description');
            $table->tinyInteger('status');
            $table->text('notify_send_to')->nullable();
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_complaints');
    }
};
