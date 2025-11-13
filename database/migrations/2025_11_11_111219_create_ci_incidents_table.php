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
        Schema::create('ci_incidents', function (Blueprint $table) {
            $table->integer('incident_id', true);
            $table->integer('company_id');
            $table->integer('employee_id');
            $table->integer('incident_type_id');
            $table->string('title', 255)->nullable();
            $table->string('incident_date', 200);
            $table->string('incident_time', 200);
            $table->string('incident_file', 255);
            $table->mediumText('description');
            $table->text('notify_send_to')->nullable();
            $table->string('created_at', 200);
            $table->string('status', 22)->nullable()->default('0');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_incidents');
    }
};
