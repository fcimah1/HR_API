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
        Schema::create('ci_contract_options', function (Blueprint $table) {
            $table->integer('company_id')->nullable();
            $table->integer('contract_option_id', true);
            $table->integer('user_id');
            $table->string('salay_type', 200)->nullable();
            $table->integer('contract_tax_option');
            $table->integer('is_fixed');
            $table->string('option_title', 200)->nullable();
            $table->decimal('contract_amount', 65)->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_contract_options');
    }
};
