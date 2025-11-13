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
        Schema::create('ci_sms_template', function (Blueprint $table) {
            $table->integer('template_id');
            $table->string('subject', 255)->nullable();
            $table->text('message')->nullable();
            $table->string('created_at', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_sms_template');
    }
};
