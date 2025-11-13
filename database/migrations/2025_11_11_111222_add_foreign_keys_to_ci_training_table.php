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
        Schema::table('ci_training', function (Blueprint $table) {
            $table->foreign(['department_id'], 'fk_training_department')->references(['department_id'])->on('ci_departments')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ci_training', function (Blueprint $table) {
            $table->dropForeign('fk_training_department');
        });
    }
};
