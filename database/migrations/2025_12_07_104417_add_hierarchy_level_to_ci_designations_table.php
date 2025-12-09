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
        Schema::table('ci_designations', function (Blueprint $table) {
            $table->tinyInteger('hierarchy_level')
                ->nullable()
                ->after('department_id')
                ->index()
                ->comment('Hierarchy level 1-5, where 1 is highest and 5 is lowest');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ci_designations', function (Blueprint $table) {
            $table->dropColumn('hierarchy_level');
        });
    }
};
