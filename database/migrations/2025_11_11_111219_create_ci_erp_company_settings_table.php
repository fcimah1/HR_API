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
        Schema::create('ci_erp_company_settings', function (Blueprint $table) {
            $table->integer('setting_id', true);
            $table->integer('company_id');
            $table->string('default_currency', 255)->default('USD');
            $table->string('default_currency_symbol', 100)->default('USD');
            $table->string('notification_position', 255)->nullable();
            $table->string('notification_close_btn', 255)->nullable();
            $table->string('notification_bar', 255)->nullable();
            $table->string('date_format_xi', 255)->nullable();
            $table->string('default_language', 200)->default('en');
            $table->string('system_timezone', 200)->default('Asia/Bishkek');
            $table->integer('enable_ip_address');
            $table->string('ip_address', 100)->nullable();
            $table->string('paypal_email', 100)->nullable();
            $table->string('paypal_sandbox', 10)->nullable();
            $table->string('paypal_active', 10)->nullable();
            $table->string('stripe_secret_key', 200)->nullable();
            $table->string('stripe_publishable_key', 200)->nullable();
            $table->string('stripe_active', 10)->nullable();
            $table->text('invoice_terms_condition')->nullable();
            $table->text('setup_modules')->nullable();
            $table->integer('is_enable_ml_payroll');
            $table->text('setup_languages')->nullable();
            $table->string('approval_levels', 200);
            $table->string('bank_account', 255)->nullable();
            $table->string('vat_number', 255)->nullable();
            $table->integer('hrm_staff_dashboard');
            $table->string('is_lunch_break', 55)->default('NO');
            $table->string('hide_org_name', 55)->default('NO');
            $table->string('hide_org_photo', 55)->default('NO');
            $table->string('updated_at', 255)->nullable();
            $table->text('map_api_key')->nullable();
            $table->text('map_api_key_server')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_erp_company_settings');
    }
};
