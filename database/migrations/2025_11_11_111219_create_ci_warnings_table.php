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
        Schema::create('ci_warnings', function (Blueprint $table) {
            $table->integer('warning_id', true);
            $table->integer('company_id');
            $table->integer('warning_to');
            $table->integer('warning_by');
            $table->string('warning_date', 255);
            $table->integer('warning_type_id');
            $table->string('attachment', 255)->nullable();
            $table->string('subject', 255);
            $table->mediumText('description');
            $table->integer('status')->nullable()->default(1);
            $table->string('notify_send_to', 255)->nullable();
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_warnings');
    }
};
