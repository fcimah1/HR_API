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
        Schema::create('ci_erp_users_role', function (Blueprint $table) {
            $table->integer('role_id', true);
            $table->string('role_name', 200)->nullable();
            $table->string('role_access', 200)->nullable();
            $table->text('role_resources')->nullable();
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_erp_users_role');
    }
};
