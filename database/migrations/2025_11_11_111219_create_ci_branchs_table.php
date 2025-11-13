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
        Schema::create('ci_branchs', function (Blueprint $table) {
            $table->integer('branch_id', true);
            $table->integer('company_id');
            $table->string('branch_name', 200);
            $table->text('description')->nullable();
            $table->string('created_at', 200);
            $table->geometry('coordinates', 'polygon')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_branchs');
    }
};
