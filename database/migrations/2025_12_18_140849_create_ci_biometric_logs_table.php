<?php

declare(strict_types=1);

use App\Enums\PunchTypeEnum;
use App\Enums\VerifyModeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * جدول سجلات البصمة الخام من أجهزة الحضور والانصراف
     */
    public function up(): void
    {
        Schema::create('ci_biometric_logs', function (Blueprint $table) {
            $table->id();

            // معرفات الشركة والفرع والموظف
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('branch_id');
            $table->string('employee_id', 50);
            $table->unsignedInteger('user_id');

            // بيانات البصمة الأساسية
            $table->dateTime('punch_time');
            $table->enum('punch_type', PunchTypeEnum::values())->comment('نوع البصمة');
            $table->enum('verify_mode', VerifyModeEnum::values())->comment('طريقة التحقق');

            // البيانات الخام من الجهاز
            $table->json('raw_data')->comment('البيانات الخام كاملة من الجهاز');

            // حالة المعالجة
            $table->boolean('is_processed')->default(false)->comment('هل تم معالجتها وتسجيلها في جدول الحضور؟');
            $table->dateTime('processed_at')->nullable()->comment('وقت المعالجة');
            $table->unsignedBigInteger('attendance_id')->nullable()->comment('معرف سجل الحضور المرتبط');
            $table->text('processing_notes')->nullable()->comment('ملاحظات المعالجة أو أي أخطاء');

            $table->timestamps();

            // الفهارس
            $table->index(['company_id', 'branch_id', 'employee_id'], 'idx_company_branch_employee');
            $table->index('punch_time', 'idx_punch_time');
            $table->index('user_id', 'idx_user_id');
            $table->index('is_processed', 'idx_is_processed');
            $table->index(['company_id', 'punch_time'], 'idx_company_punch_time');
        });
    }

    /**+
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ci_biometric_logs');
    }
};
