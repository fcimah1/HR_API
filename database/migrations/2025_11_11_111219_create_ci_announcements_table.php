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
        Schema::create('ci_announcements', function (Blueprint $table) {
            $table->integer('announcement_id', true);
            $table->integer('company_id');
            $table->string('department_id', 255);
            $table->string('audience_id', 255)->nullable();
            $table->string('title', 200);
            $table->string('start_date', 200);
            $table->string('end_date', 200);
            $table->integer('published_by');
            $table->mediumText('summary');
            $table->mediumText('description');
            $table->boolean('is_active');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_announcements');
    }
};
