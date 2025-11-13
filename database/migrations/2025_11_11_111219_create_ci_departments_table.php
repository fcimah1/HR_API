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
        Schema::create('ci_departments', function (Blueprint $table) {
            $table->integer('department_id', true);
            $table->string('department_name', 200);
            $table->integer('company_id');
            $table->integer('department_head')->nullable()->default(0);
            $table->integer('added_by');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_departments');
    }
};
