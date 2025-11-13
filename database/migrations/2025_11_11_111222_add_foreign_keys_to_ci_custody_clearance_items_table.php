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
        Schema::table('ci_custody_clearance_items', function (Blueprint $table) {
            $table->foreign(['clearance_id'], 'fk_clearance_items_clearance')->references(['clearance_id'])->on('ci_custody_clearance')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ci_custody_clearance_items', function (Blueprint $table) {
            $table->dropForeign('fk_clearance_items_clearance');
        });
    }
};
