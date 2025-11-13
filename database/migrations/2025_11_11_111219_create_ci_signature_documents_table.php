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
        Schema::create('ci_signature_documents', function (Blueprint $table) {
            $table->integer('document_id', true);
            $table->integer('company_id');
            $table->integer('folder_id');
            $table->integer('share_with_employees')->default(1);
            $table->string('document_file', 255);
            $table->string('document_name', 255)->nullable();
            $table->string('document_size', 100)->nullable();
            $table->integer('signature_task');
            $table->string('created_at', 200)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_signature_documents');
    }
};
