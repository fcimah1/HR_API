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
        Schema::create('ci_staff_roles', function (Blueprint $table) {
            $table->integer('role_id', true);
            $table->integer('company_id');
            $table->string('role_name', 200);
            $table->string('role_access', 200);
            $table->longText('role_resources');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_staff_roles');
    }
};
