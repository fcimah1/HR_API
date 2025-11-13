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
        Schema::create('ci_leads_followup', function (Blueprint $table) {
            $table->integer('followup_id', true);
            $table->integer('lead_id');
            $table->integer('company_id');
            $table->string('next_followup', 255);
            $table->text('description');
            $table->string('created_at', 255);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_leads_followup');
    }
};
