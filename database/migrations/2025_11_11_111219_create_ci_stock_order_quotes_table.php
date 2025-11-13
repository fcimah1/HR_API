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
        Schema::create('ci_stock_order_quotes', function (Blueprint $table) {
            $table->integer('quote_id', true);
            $table->string('quote_number', 255);
            $table->integer('company_id');
            $table->integer('customer_id');
            $table->string('quote_month', 255)->nullable();
            $table->string('quote_date', 255);
            $table->string('quote_due_date', 255);
            $table->decimal('sub_total_amount', 65)->default(0);
            $table->string('discount_type', 11);
            $table->decimal('discount_figure', 65)->default(0);
            $table->decimal('total_tax', 65)->default(0);
            $table->string('tax_type', 100)->nullable();
            $table->decimal('total_discount', 65)->default(0);
            $table->decimal('grand_total', 65)->default(0);
            $table->mediumText('quote_note');
            $table->boolean('status');
            $table->integer('payment_method');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_stock_order_quotes');
    }
};
