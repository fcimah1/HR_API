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
        Schema::create('ci_travels', function (Blueprint $table) {
            $table->integer('travel_id', true);
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->string('start_date', 255);
            $table->string('end_date', 255);
            $table->text('associated_goals')->nullable();
            $table->string('visit_purpose', 255);
            $table->string('visit_place', 255);
            $table->integer('travel_mode')->nullable();
            $table->integer('arrangement_type')->nullable();
            $table->decimal('expected_budget', 65)->default(0);
            $table->decimal('actual_budget', 65)->default(0);
            $table->mediumText('description');
            $table->tinyInteger('status');
            $table->integer('added_by');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_travels');
    }
};
