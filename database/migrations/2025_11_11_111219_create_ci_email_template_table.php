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
        Schema::create('ci_email_template', function (Blueprint $table) {
            $table->integer('template_id', true);
            $table->string('template_code', 255);
            $table->string('template_type', 100);
            $table->string('name', 255);
            $table->string('subject', 255);
            $table->longText('message');
            $table->tinyInteger('status');
            $table->string('lang', 20)->default('en');
            $table->integer('iddse')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_email_template');
    }
};
