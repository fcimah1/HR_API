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
        Schema::create('ci_membership', function (Blueprint $table) {
            $table->integer('membership_id', true);
            $table->string('subscription_id', 100)->nullable();
            $table->string('membership_type', 200);
            $table->decimal('price', 65)->default(0);
            $table->integer('plan_duration');
            $table->integer('total_employees')->default(0);
            $table->string('plan_icon', 100)->nullable();
            $table->mediumText('description');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_membership');
    }
};
