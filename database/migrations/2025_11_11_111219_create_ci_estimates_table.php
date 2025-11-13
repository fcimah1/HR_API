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
        Schema::create('ci_estimates', function (Blueprint $table) {
            $table->integer('estimate_id', true);
            $table->string('estimate_number', 255);
            $table->integer('company_id');
            $table->integer('client_id');
            $table->integer('project_id');
            $table->string('estimate_month', 255)->nullable();
            $table->string('estimate_date', 255);
            $table->string('estimate_due_date', 255);
            $table->decimal('sub_total_amount', 65)->default(0);
            $table->string('discount_type', 11);
            $table->decimal('discount_figure', 65)->default(0);
            $table->decimal('total_tax', 65)->default(0);
            $table->string('tax_type', 100)->nullable();
            $table->decimal('total_discount', 65)->default(0);
            $table->decimal('grand_total', 65)->default(0);
            $table->mediumText('estimate_note');
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
        Schema::dropIfExists('ci_estimates');
    }
};
