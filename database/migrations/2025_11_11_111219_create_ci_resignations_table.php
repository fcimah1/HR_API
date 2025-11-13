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
        Schema::create('ci_resignations', function (Blueprint $table) {
            $table->integer('resignation_id', true);
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->string('notice_date', 255);
            $table->string('resignation_date', 255);
            $table->string('document_file', 255)->nullable();
            $table->integer('is_signed');
            $table->string('signed_file', 255)->nullable();
            $table->string('signed_date', 255)->nullable();
            $table->mediumText('reason');
            $table->integer('added_by');
            $table->integer('status');
            $table->text('notify_send_to')->nullable();
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_resignations');
    }
};
