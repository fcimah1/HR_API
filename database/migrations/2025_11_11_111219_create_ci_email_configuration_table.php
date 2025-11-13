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
        Schema::create('ci_email_configuration', function (Blueprint $table) {
            $table->integer('email_config_id', true);
            $table->enum('email_type', ['smtp', 'mailgun']);
            $table->string('smtp_host', 64)->nullable();
            $table->string('smtp_username', 64)->nullable();
            $table->string('smtp_password', 64)->nullable();
            $table->integer('smtp_port');
            $table->enum('smtp_secure', ['tls', 'ssl'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_email_configuration');
    }
};
