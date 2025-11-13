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
        Schema::create('ci_company_tickets_reply', function (Blueprint $table) {
            $table->integer('ticket_reply_id', true);
            $table->integer('company_id');
            $table->integer('ticket_id');
            $table->integer('sent_by');
            $table->integer('assign_to');
            $table->text('reply_text')->nullable();
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_company_tickets_reply');
    }
};
