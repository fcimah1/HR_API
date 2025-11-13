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
        Schema::create('ci_custody_clearance', function (Blueprint $table) {
            $table->integer('clearance_id', true);
            $table->integer('company_id')->index('company_id');
            $table->integer('employee_id')->index('employee_id');
            $table->date('clearance_date');
            $table->enum('clearance_type', ['resignation', 'termination', 'transfer', 'other'])->default('resignation');
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index('status');
            $table->integer('approved_by')->nullable();
            $table->dateTime('approved_date')->nullable();
            $table->integer('created_by');
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_custody_clearance');
    }
};
