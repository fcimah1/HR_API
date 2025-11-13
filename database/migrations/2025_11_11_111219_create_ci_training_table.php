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
        Schema::create('ci_training', function (Blueprint $table) {
            $table->integer('training_id', true);
            $table->integer('company_id');
            $table->integer('department_id')->nullable()->index('idx_department_id');
            $table->string('employee_id', 200);
            $table->integer('training_type_id');
            $table->text('associated_goals')->nullable();
            $table->integer('trainer_id');
            $table->string('start_date', 200);
            $table->string('finish_date', 200);
            $table->decimal('training_cost', 65)->nullable();
            $table->integer('training_status')->nullable();
            $table->mediumText('description')->nullable();
            $table->string('performance', 200)->nullable();
            $table->mediumText('remarks')->nullable();
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_training');
    }
};
