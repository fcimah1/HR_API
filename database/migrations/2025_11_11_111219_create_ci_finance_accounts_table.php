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
        Schema::create('ci_finance_accounts', function (Blueprint $table) {
            $table->integer('account_id', true);
            $table->integer('company_id');
            $table->string('account_name', 255);
            $table->decimal('account_balance', 65)->default(0);
            $table->decimal('account_opening_balance', 65)->default(0);
            $table->string('account_number', 255);
            $table->string('branch_code', 255);
            $table->text('bank_branch');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_finance_accounts');
    }
};
