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
        Schema::create('ci_form_send', function (Blueprint $table) {
            $table->integer('form_send_id', true);
            $table->integer('company_id');
            $table->integer('form_id');
            $table->string('assigned_to', 255);
            $table->text('form_deadline');
            $table->integer('status')->default(0);
            $table->string('created_at', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_form_send');
    }
};
