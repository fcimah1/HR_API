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
        if (!Schema::hasTable('ci_operation_restrictions')) {
            Schema::create('ci_operation_restrictions', function (Blueprint $table) {
                $table->integer('restriction_id')->autoIncrement();
                $table->integer('company_id')->index();
                $table->integer('user_id')->comment('Employee user_id from ci_erp_users')->index();
                $table->text('restricted_operations')->nullable()->comment('Comma-separated list of restricted operation keys');
                $table->integer('created_by')->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['company_id', 'user_id'], 'unique_user_restriction');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_operation_restrictions');
    }
};
