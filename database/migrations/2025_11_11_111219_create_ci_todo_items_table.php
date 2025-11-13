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
        Schema::create('ci_todo_items', function (Blueprint $table) {
            $table->integer('todo_item_id', true);
            $table->integer('company_id');
            $table->integer('user_id');
            $table->mediumText('description')->nullable();
            $table->integer('is_done');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_todo_items');
    }
};
