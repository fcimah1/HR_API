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
        Schema::create('ci_erp_system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level')->index(); // info, error, warning, etc.
            $table->text('message');
            $table->json('context')->nullable(); // لتخزين البيانات الإضافية (array)

            // بيانات التتبع
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method')->nullable(); // GET, POST, etc.
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_erp_system_logs');
    }
};
