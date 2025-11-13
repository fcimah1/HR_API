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
        Schema::create('ci_staff_signature_documents', function (Blueprint $table) {
            $table->integer('document_id', true);
            $table->integer('company_id');
            $table->integer('staff_id');
            $table->integer('signature_file_id');
            $table->string('signature_task', 100);
            $table->integer('is_signed');
            $table->string('signed_file', 255)->nullable();
            $table->string('signed_date', 100)->nullable();
            $table->string('created_at', 100);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_staff_signature_documents');
    }
};
