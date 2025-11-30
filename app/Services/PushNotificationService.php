<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\User;

/**
 * Push Notification Service using Firebase Cloud Messaging (FCM)
 * 
 * To use this service:
 * 1. Add FCM server key to .env: FCM_SERVER_KEY=your-key
 * 2. Store device tokens in users table or separate table
 * 3. Uncomment the actual HTTP request code
 */
class PushNotificationService
{
    protected string $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
    protected ?string $serverKey;

    public function __construct()
    {
        $this->serverKey = env('FCM_SERVER_KEY');
    }

    /**
     * Send push notification to user
     */
    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        array $data = []
    ): bool {
        try {
            $user = User::find($userId);

            if (!$user || !$user->device_token) {
                Log::warning('No device token for user', ['user_id' => $userId]);
                return false;
            }

            return $this->send($user->device_token, $title, $body, $data);
        } catch (\Exception $e) {
            Log::error('Failed to send push notification', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send push notification to multiple users
     */
    public function sendToUsers(
        array $userIds,
        string $title,
        string $body,
        array $data = []
    ): int {
        $sent = 0;

        foreach ($userIds as $userId) {
            if ($this->sendToUser($userId, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send push notification to specific device token
     */
    protected function send(
        string $deviceToken,
        string $title,
        string $body,
        array $data = []
    ): bool {
        if (!$this->serverKey) {
            Log::warning('FCM server key not configured');
            return false;
        }

        $payload = [
            'to' => $deviceToken,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
            ],
            'data' => $data,
            'priority' => 'high',
        ];

        // TODO: Uncomment when FCM is configured
        /*
        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->fcmUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
        */

        Log::info('Push notification would be sent (FCM not configured)', [
            'title' => $title,
            'body' => $body,
        ]);

        return true; // Simulated success
    }

    /**
     * Send submission notification
     */
    public function sendSubmissionPush(
        array $recipientIds,
        string $moduleTitle,
        string $submitterName
    ): int {
        $title = "طلب جديد - {$moduleTitle}";
        $body = "قام {$submitterName} بإرسال طلب جديد";

        return $this->sendToUsers($recipientIds, $title, $body, [
            'type' => 'submission',
            'module' => $moduleTitle,
        ]);
    }

    /**
     * Send approval notification
     */
    public function sendApprovalPush(
        array $recipientIds,
        string $moduleTitle,
        string $status
    ): int {
        $statusText = $status === 'approved' ? 'تمت الموافقة' : 'تم الرفض';
        $title = "{$statusText} - {$moduleTitle}";
        $body = "تم {$statusText} على طلبك";

        return $this->sendToUsers($recipientIds, $title, $body, [
            'type' => 'approval_result',
            'module' => $moduleTitle,
            'status' => $status,
        ]);
    }
}
