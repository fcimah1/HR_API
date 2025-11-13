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
        Schema::create('ci_company_membership', function (Blueprint $table) {
            $table->integer('company_membership_id', true);
            $table->integer('company_id');
            $table->integer('membership_id');
            $table->string('subscription_type', 25);
            $table->string('update_at', 100)->nullable();
            $table->string('created_at', 100);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_company_membership');
    }
};
