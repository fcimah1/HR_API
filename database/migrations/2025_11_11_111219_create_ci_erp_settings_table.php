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
        Schema::create('ci_erp_settings', function (Blueprint $table) {
            $table->integer('setting_id', true);
            $table->string('application_name', 255);
            $table->string('company_name', 100)->nullable();
            $table->string('trading_name', 100)->nullable();
            $table->string('registration_no', 100)->nullable();
            $table->string('government_tax', 100)->nullable();
            $table->integer('company_type_id');
            $table->string('email', 200)->nullable();
            $table->string('contact_number', 255)->nullable();
            $table->integer('country')->default(0);
            $table->text('address_1')->nullable();
            $table->text('address_2')->nullable();
            $table->string('city', 200)->nullable();
            $table->string('zipcode', 200)->nullable();
            $table->string('state', 200)->nullable();
            $table->string('default_currency', 255)->default('USD');
            $table->string('is_ssl_available', 11)->default('on');
            $table->mediumText('currency_converter')->nullable();
            $table->string('notification_position', 255);
            $table->string('notification_close_btn', 255);
            $table->string('notification_bar', 255);
            $table->string('date_format_xi', 255);
            $table->string('enable_email_notification', 255);
            $table->string('email_type', 100);
            $table->string('logo', 200);
            $table->string('favicon', 200);
            $table->string('frontend_logo', 200);
            $table->string('other_logo', 255)->nullable();
            $table->string('animation_effect', 255);
            $table->string('animation_effect_modal', 255);
            $table->string('animation_effect_topmenu', 255);
            $table->string('default_language', 200)->default('en');
            $table->string('system_timezone', 200)->default('Asia/Bishkek');
            $table->string('paypal_email', 100);
            $table->string('paypal_sandbox', 10);
            $table->string('paypal_active', 10);
            $table->string('stripe_secret_key', 200);
            $table->string('stripe_publishable_key', 200);
            $table->string('stripe_active', 10);
            $table->text('razorpay_keyid');
            $table->text('razorpay_key_secret');
            $table->string('razorpay_active', 100)->default('yes');
            $table->text('paystack_key_secret')->nullable();
            $table->text('paystack_public_secret')->nullable();
            $table->string('paystack_active', 11)->default('no');
            $table->text('flutterwave_public_key')->nullable();
            $table->text('flutterwave_secret_key')->nullable();
            $table->string('flutterwave_active', 10)->default('no');
            $table->integer('online_payment_account');
            $table->integer('tax_type');
            $table->integer('enable_tax')->default(0);
            $table->text('invoice_terms_condition')->nullable();
            $table->string('auth_background', 255)->nullable();
            $table->integer('enable_sms_notification')->default(0);
            $table->string('sms_from', 100)->nullable();
            $table->text('sms_service_plan_id')->nullable();
            $table->text('sms_bearer_token')->nullable();
            $table->string('header_background', 50)->default('bg-dark');
            $table->integer('login_page')->default(1);
            $table->text('login_page_text')->nullable();
            $table->integer('light_sidebar')->default(1);
            $table->integer('template_option');
            $table->string('hr_version', 200);
            $table->string('hr_release_date', 100);
            $table->string('updated_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_erp_settings');
    }
};
