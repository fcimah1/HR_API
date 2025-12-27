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
        Schema::table('ci_transfers', function (Blueprint $table) {
            // ✅ جيد - لكن أقترح إضافة:
            $table->text('custody_clearance_notes')->nullable()->after('transfer_type')->comment('ملاحظات إخلاء الطرف');
            $table->json('blocked_reasons')->nullable()->after('custody_clearance_notes')->comment('أسباب منع التنفيذ (عهد، إجازات، سلف)');
            $table->datetime('executed_at')->nullable()->after('blocked_reasons')->comment('تاريخ تنفيذ النقل');
            $table->integer('executed_by')->nullable()->after('executed_at')->comment('من قام بتنفيذ النقل');
            $table->text('validation_notes')->nullable()->after('executed_by')->comment('ملاحظات التحقق من المتطلبات');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ci_transfers', function (Blueprint $table) {
            $table->dropColumn(['executed_at', 'executed_by', 'validation_notes', 'custody_clearance_notes', 'blocked_reasons']);
        });
    }
};
