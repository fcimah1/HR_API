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
        Schema::create('ci_chat_messages', function (Blueprint $table) {
            $table->integer('message_id', true);
            $table->integer('company_id');
            $table->integer('from_id');
            $table->integer('to_id');
            $table->string('message_frm', 255);
            $table->tinyInteger('is_read')->default(0);
            $table->text('message_content');
            $table->text('recd')->nullable();
            $table->string('message_type', 255)->nullable();
            $table->string('message_date', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_chat_messages');
    }
};
