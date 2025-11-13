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
        Schema::create('ci_finance_membership_invoices', function (Blueprint $table) {
            $table->integer('membership_invoice_id', true);
            $table->string('invoice_id', 50)->nullable();
            $table->integer('company_id');
            $table->integer('membership_id');
            $table->string('subscription_id', 50)->nullable();
            $table->string('membership_type', 200);
            $table->string('subscription', 200);
            $table->string('invoice_month', 255)->nullable();
            $table->decimal('membership_price', 65)->default(0);
            $table->string('payment_method', 200);
            $table->string('transaction_date', 200);
            $table->mediumText('description');
            $table->longText('receipt_url')->nullable();
            $table->string('source_info', 10)->nullable();
            $table->string('created_at', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_finance_membership_invoices');
    }
};
