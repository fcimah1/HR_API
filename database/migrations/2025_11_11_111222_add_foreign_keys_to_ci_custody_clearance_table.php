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
        Schema::table('ci_custody_clearance', function (Blueprint $table) {
            $table->foreign(['employee_id'], 'fk_custody_clearance_employee')->references(['user_id'])->on('ci_erp_users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ci_custody_clearance', function (Blueprint $table) {
            $table->dropForeign('fk_custody_clearance_employee');
        });
    }
};
