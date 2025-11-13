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
        Schema::create('ci_invoices', function (Blueprint $table) {
            $table->integer('invoice_id', true);
            $table->string('invoice_number', 255);
            $table->integer('company_id');
            $table->integer('client_id');
            $table->integer('project_id');
            $table->string('invoice_month', 255)->nullable();
            $table->string('invoice_date', 255);
            $table->string('invoice_due_date', 255);
            $table->decimal('sub_total_amount', 65)->default(0);
            $table->string('discount_type', 11);
            $table->decimal('discount_figure', 65)->default(0);
            $table->decimal('total_tax', 65)->default(0);
            $table->string('tax_type', 100)->nullable();
            $table->decimal('total_discount', 65)->default(0);
            $table->decimal('grand_total', 65)->default(0);
            $table->mediumText('invoice_note');
            $table->boolean('status');
            $table->integer('payment_method');
            $table->string('payment_date', 100)->nullable();
            $table->integer('deposit_to');
            $table->integer('with_holding_taxid');
            $table->decimal('with_holding_tax', 65);
            $table->integer('pay_full');
            $table->decimal('total_amount', 65);
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_invoices');
    }
};
