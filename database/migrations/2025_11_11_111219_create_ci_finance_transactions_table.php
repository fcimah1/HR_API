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
        Schema::create('ci_finance_transactions', function (Blueprint $table) {
            $table->integer('transaction_id', true);
            $table->integer('account_id');
            $table->integer('company_id');
            $table->integer('staff_id');
            $table->string('transaction_date', 255);
            $table->string('transaction_type', 100);
            $table->integer('entity_id');
            $table->string('entity_type', 100)->nullable();
            $table->integer('entity_category_id');
            $table->mediumText('description');
            $table->decimal('amount', 65)->default(0);
            $table->enum('dr_cr', ['dr', 'cr']);
            $table->integer('payment_method_id');
            $table->string('reference', 100)->nullable();
            $table->string('attachment_file', 100)->nullable();
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_finance_transactions');
    }
};
