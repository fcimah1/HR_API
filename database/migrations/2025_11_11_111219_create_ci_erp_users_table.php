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
        Schema::create('ci_erp_users', function (Blueprint $table) {
            $table->integer('user_id', true);
            $table->integer('user_role_id')->nullable();
            $table->string('user_type', 50);
            $table->integer('company_id');
            $table->string('first_name', 255);
            $table->string('last_name', 255);
            $table->string('email', 255);
            $table->string('username', 255);
            $table->string('password', 255);
            $table->string('company_name', 100)->nullable();
            $table->string('trading_name', 100)->nullable();
            $table->string('registration_no', 100)->nullable();
            $table->string('government_tax', 100)->nullable();
            $table->integer('company_type_id')->nullable();
            $table->string('profile_photo', 255);
            $table->string('contact_number', 255)->nullable();
            $table->string('gender', 20);
            $table->text('address_1')->nullable();
            $table->text('address_2')->nullable();
            $table->string('city', 255)->nullable();
            $table->string('state', 255)->nullable();
            $table->string('zipcode', 255)->nullable();
            $table->integer('country')->nullable();
            $table->string('last_login_date', 255)->nullable();
            $table->string('last_logout_date', 200)->nullable();
            $table->string('last_login_ip', 255)->nullable();
            $table->integer('is_logged_in')->nullable();
            $table->integer('is_active')->default(1);
            $table->string('kiosk_code', 4)->nullable();
            $table->string('created_at', 255);
            $table->string('fiscal_date', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_erp_users');
    }
};
