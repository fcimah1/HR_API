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
        Schema::create('ci_custody_clearance_items', function (Blueprint $table) {
            $table->integer('item_id', true);
            $table->integer('clearance_id')->index('clearance_id');
            $table->integer('asset_id')->index('asset_id');
            $table->enum('asset_condition', ['good', 'damaged', 'lost'])->default('good');
            $table->date('return_date');
            $table->text('notes')->nullable();
            $table->dateTime('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_custody_clearance_items');
    }
};
