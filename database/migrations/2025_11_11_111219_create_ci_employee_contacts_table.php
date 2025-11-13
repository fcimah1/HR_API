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
        Schema::create('ci_employee_contacts', function (Blueprint $table) {
            $table->integer('contact_id', true);
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->string('relation', 255)->nullable();
            $table->integer('is_primary')->nullable();
            $table->integer('is_dependent')->nullable();
            $table->string('contact_name', 255)->nullable();
            $table->string('work_phone', 255)->nullable();
            $table->string('work_phone_extension', 255)->nullable();
            $table->string('mobile_phone', 255)->nullable();
            $table->string('home_phone', 255)->nullable();
            $table->string('work_email', 255)->nullable();
            $table->string('personal_email', 255)->nullable();
            $table->mediumText('address_1')->nullable();
            $table->mediumText('address_2')->nullable();
            $table->string('city', 255)->nullable();
            $table->string('state', 255)->nullable();
            $table->string('zipcode', 255)->nullable();
            $table->string('country', 255)->nullable();
            $table->string('created_at', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_employee_contacts');
    }
};
