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
        Schema::create('ci_awards', function (Blueprint $table) {
            $table->integer('award_id', true);
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->integer('award_type_id');
            $table->text('associated_goals')->nullable();
            $table->string('gift_item', 200);
            $table->decimal('cash_price', 65);
            $table->string('award_photo', 255);
            $table->string('award_month_year', 200);
            $table->mediumText('award_information');
            $table->mediumText('description');
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_awards');
    }
};
