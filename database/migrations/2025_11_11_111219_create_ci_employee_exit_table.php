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
        Schema::create('ci_employee_exit', function (Blueprint $table) {
            $table->integer('exit_id', true);
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->string('exit_date', 255);
            $table->integer('exit_type_id');
            $table->integer('exit_interview');
            $table->integer('is_inactivate_account');
            $table->mediumText('reason');
            $table->integer('added_by');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_employee_exit');
    }
};
