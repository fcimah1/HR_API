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
        Schema::create('ci_visitors', function (Blueprint $table) {
            $table->integer('visitor_id', true);
            $table->integer('company_id');
            $table->integer('department_id');
            $table->string('visit_purpose', 255)->nullable();
            $table->string('visitor_name', 255)->nullable();
            $table->string('phone', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('visit_date', 255)->nullable();
            $table->string('check_in', 255)->nullable();
            $table->string('check_out', 255)->nullable();
            $table->mediumText('address')->nullable();
            $table->mediumText('description')->nullable();
            $table->integer('created_by');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_visitors');
    }
};
