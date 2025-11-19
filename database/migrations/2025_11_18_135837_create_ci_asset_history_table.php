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
        Schema::create('ci_asset_history', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('asset_id');
            $table->integer('company_id');
            $table->integer('employee_id')->default(0);
            $table->string('action', 50); // assigned, unassigned, updated, reported, status_changed
            $table->integer('changed_by');
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_at', 200);
            
            // Indexes
            $table->index('asset_id');
            $table->index('company_id');
            $table->index('employee_id');
            $table->index('changed_by');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_asset_history');
    }
};

