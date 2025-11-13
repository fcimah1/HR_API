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
        Schema::create('ci_frontend_content', function (Blueprint $table) {
            $table->integer('frontend_id', true);
            $table->string('youtube_url', 255)->nullable();
            $table->text('about_short')->nullable();
            $table->text('fb_profile')->nullable();
            $table->text('twitter_profile')->nullable();
            $table->text('linkedin_profile')->nullable();
            $table->string('updated_at', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_frontend_content');
    }
};
