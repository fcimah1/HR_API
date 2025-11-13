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
        Schema::create('ci_company_tickets', function (Blueprint $table) {
            $table->integer('ticket_id', true);
            $table->integer('company_id');
            $table->string('ticket_code', 200);
            $table->string('subject', 255);
            $table->string('ticket_priority', 255);
            $table->integer('category_id');
            $table->mediumText('description');
            $table->mediumText('ticket_remarks')->nullable();
            $table->string('ticket_status', 200);
            $table->integer('created_by');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_company_tickets');
    }
};
