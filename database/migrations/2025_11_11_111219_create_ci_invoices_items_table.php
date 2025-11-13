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
        Schema::create('ci_invoices_items', function (Blueprint $table) {
            $table->integer('invoice_item_id', true);
            $table->integer('invoice_id');
            $table->integer('project_id');
            $table->string('item_name', 255);
            $table->string('item_qty', 255);
            $table->decimal('item_unit_price', 65)->default(0);
            $table->decimal('item_sub_total', 65)->default(0);
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_invoices_items');
    }
};
