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
        Schema::create('ci_assets', function (Blueprint $table) {
            $table->integer('assets_id', true);
            $table->integer('assets_category_id');
            $table->integer('brand_id');
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->string('company_asset_code', 255);
            $table->string('name', 255);
            $table->string('purchase_date', 255);
            $table->string('invoice_number', 255);
            $table->string('manufacturer', 255);
            $table->string('serial_number', 255);
            $table->string('warranty_end_date', 255);
            $table->text('asset_note');
            $table->string('asset_image', 255);
            $table->integer('is_working');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_assets');
    }
};
