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
        Schema::create('ci_announcement_comments', function (Blueprint $table) {
            $table->integer('comment_id', true);
            $table->integer('company_id');
            $table->integer('announcement_id');
            $table->integer('employee_id');
            $table->text('announcement_comment')->nullable();
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_announcement_comments');
    }
};
