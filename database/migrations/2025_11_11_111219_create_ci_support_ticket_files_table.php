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
        Schema::create('ci_support_ticket_files', function (Blueprint $table) {
            $table->integer('ticket_file_id', true);
            $table->integer('company_id');
            $table->integer('ticket_id');
            $table->integer('employee_id');
            $table->string('file_title', 255);
            $table->mediumText('attachment_file');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_support_ticket_files');
    }
};
