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
        Schema::create('ci_designations', function (Blueprint $table) {
            $table->integer('designation_id', true);
            $table->integer('department_id');
            $table->integer('company_id');
            $table->tinyInteger('hierarchy_level')->default(5)->comment('Hierarchy level: 1=highest, 5=lowest');
            $table->string('designation_name', 200);
            $table->text('description');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_designations');
    }
};
