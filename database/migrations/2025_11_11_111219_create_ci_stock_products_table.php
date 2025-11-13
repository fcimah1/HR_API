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
        Schema::create('ci_stock_products', function (Blueprint $table) {
            $table->integer('product_id', true);
            $table->integer('company_id');
            $table->string('product_name', 255)->nullable();
            $table->integer('product_qty');
            $table->integer('reorder_stock');
            $table->string('barcode', 255)->nullable();
            $table->string('barcode_type', 255)->nullable();
            $table->integer('warehouse_id');
            $table->integer('category_id');
            $table->string('product_sku', 255)->nullable();
            $table->string('product_serial_number', 255)->nullable();
            $table->decimal('purchase_price', 65);
            $table->decimal('retail_price', 65);
            $table->string('expiration_date', 255)->nullable();
            $table->string('product_image', 255)->nullable();
            $table->longText('product_description')->nullable();
            $table->integer('product_rating');
            $table->integer('added_by');
            $table->string('created_at', 255)->nullable();
            $table->boolean('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_stock_products');
    }
};
