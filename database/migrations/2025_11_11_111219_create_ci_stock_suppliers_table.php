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
        Schema::create('ci_stock_suppliers', function (Blueprint $table) {
            $table->integer('supplier_id', true);
            $table->integer('company_id');
            $table->string('supplier_name', 255);
            $table->string('registration_no', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('contact_number', 255)->nullable();
            $table->string('website_url', 255)->nullable();
            $table->text('address_1')->nullable();
            $table->text('address_2')->nullable();
            $table->string('city', 255)->nullable();
            $table->string('state', 255)->nullable();
            $table->string('zipcode', 255)->nullable();
            $table->integer('country');
            $table->integer('added_by');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_stock_suppliers');
    }
};
