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
        Schema::create('ci_erp_generated_reports', function (Blueprint $table) {
            $table->id('report_id');

            // Foreign Keys
            $table->unsignedBigInteger('user_id')->comment('الموظف الطالب');
            $table->unsignedBigInteger('company_id')->comment('الشركة');

            // Report Details
            $table->string('report_type', 100)->comment('نوع التقرير: attendance_monthly, timesheet, etc.');
            $table->string('report_title', 255)->comment('عنوان التقرير');

            // File Information
            $table->string('file_path', 500)->nullable()->comment('مسار الملف في Storage');
            $table->unsignedBigInteger('file_size')->nullable()->comment('حجم الملف بالـBytes');

            // Status
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending')
                ->comment('حالة التوليد');

            // Filters & Error
            $table->json('filters')->nullable()->comment('الفلاتر المستخدمة');
            $table->text('error_message')->nullable()->comment('رسالة الخطأ في حالة الفشل');

            // Timestamps
            $table->timestamp('started_at')->nullable()->comment('وقت البداية');
            $table->timestamp('completed_at')->nullable()->comment('وقت الانتهاء');
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'company_id']);
            $table->index(['status', 'created_at']);
            $table->index('report_type');

            // Foreign Key Constraints
            $table->foreign('user_id')->references('user_id')->on('ci_erp_users')->onDelete('cascade');
            $table->foreign('company_id')->references('company_id')->on('ci_companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_erp_generated_reports');
    }
};
