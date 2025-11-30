<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class EmailNotificationService
{
    /**
     * Send submission notification email
     */
    public function sendSubmissionEmail(
        array $recipients,
        string $moduleOption,
        string $moduleKeyId,
        array $submitterInfo
    ): int {
        $sent = 0;

        foreach ($recipients as $recipientId) {
            try {
                $user = User::find($recipientId);

                if (!$user || !$user->email) {
                    continue;
                }

                Mail::raw(
                    $this->buildSubmissionMessage($moduleOption, $moduleKeyId, $submitterInfo),
                    function ($message) use ($user, $moduleOption) {
                        $message->to($user->email)
                            ->subject($this->getModuleTitle($moduleOption) . ' - طلب جديد');
                    }
                );

                $sent++;
            } catch (\Exception $e) {
                Log::error('Failed to send submission email', [
                    'recipient_id' => $recipientId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Send approval notification email
     */
    public function sendApprovalEmail(
        array $recipients,
        string $moduleOption,
        string $moduleKeyId,
        string $status
    ): int {
        $sent = 0;

        foreach ($recipients as $recipientId) {
            try {
                $user = User::find($recipientId);

                if (!$user || !$user->email) {
                    continue;
                }

                Mail::raw(
                    $this->buildApprovalMessage($moduleOption, $moduleKeyId, $status),
                    function ($message) use ($user, $moduleOption, $status) {
                        $message->to($user->email)
                            ->subject($this->getModuleTitle($moduleOption) . ' - ' . $this->getStatusTitle($status));
                    }
                );

                $sent++;
            } catch (\Exception $e) {
                Log::error('Failed to send approval email', [
                    'recipient_id' => $recipientId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Build submission message
     */
    protected function buildSubmissionMessage(string $moduleOption, string $moduleKeyId, array $submitterInfo): string
    {
        $moduleTitle = $this->getModuleTitle($moduleOption);

        return "تم إرسال طلب جديد\n\n" .
            "النوع: {$moduleTitle}\n" .
            "رقم الطلب: {$moduleKeyId}\n" .
            "المقدم من: {$submitterInfo['name']}\n\n" .
            "يرجى مراجعة الطلب واتخاذ الإجراء المناسب.";
    }

    /**
     * Build approval message
     */
    protected function buildApprovalMessage(string $moduleOption, string $moduleKeyId, string $status): string
    {
        $moduleTitle = $this->getModuleTitle($moduleOption);
        $statusTitle = $this->getStatusTitle($status);

        return "تحديث حالة الطلب\n\n" .
            "النوع: {$moduleTitle}\n" .
            "رقم الطلب: {$moduleKeyId}\n" .
            "الحالة: {$statusTitle}";
    }

    /**
     * Get module title in Arabic
     */
    protected function getModuleTitle(string $moduleOption): string
    {
        $titles = [
            'attendance_settings' => 'الحضور',
            'leave_settings' => 'الإجازات',
            'travel_settings' => 'السفر',
            'overtime_request_settings' => 'الوقت الإضافي',
            'loan_request_settings' => 'السلف',
            'incident_settings' => 'الحوادث',
            'transfer_settings' => 'النقل',
            'warning_settings' => 'التحذيرات',
            'resignation_settings' => 'الاستقالة',
            'complaint_settings' => 'الشكاوى',
        ];

        return $titles[$moduleOption] ?? 'الطلب';
    }

    /**
     * Get status title in Arabic
     */
    protected function getStatusTitle(string $status): string
    {
        $titles = [
            'approved' => 'تمت الموافقة',
            'rejected' => 'تم الرفض',
            'pending' => 'قيد الانتظار',
        ];

        return $titles[$status] ?? $status;
    }
}
