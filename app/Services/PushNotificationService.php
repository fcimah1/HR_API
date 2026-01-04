<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\User;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;

/**
 * Push Notification Service using Firebase Cloud Messaging (FCM V1 API)
 *
 * To use this service:
 * 1. Place firebase_credentials.json in storage/app/
 * 2. Ensure google/auth is installed via composer
 */
class PushNotificationService
{
    // V1 Endpoint: https://fcm.googleapis.com/v1/projects/{projectId}/messages:send
    protected string $fcmUrlPattern = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    protected ?string $projectId = null;
    protected ?string $credentialsPath;

    public function __construct()
    {
        $this->credentialsPath = storage_path('app/firebase_credentials.json');
        $this->initializeProjectId();
    }

    /**
     * Parse project ID from credentials file locally
     */
    protected function initializeProjectId(): void
    {
        if (file_exists($this->credentialsPath)) {
            $data = json_decode(file_get_contents($this->credentialsPath), true);
            $this->projectId = $data['project_id'] ?? null;
        }
    }

    /**
     * Get OAuth2 Access Token from Google
     */
    protected function getAccessToken(): ?string
    {
        if (!file_exists($this->credentialsPath)) {
            Log::error('Firebase credentials file not found at: ' . $this->credentialsPath);
            return null;
        }

        try {
            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/firebase.messaging'],
                $this->credentialsPath
            );
            $token = $credentials->fetchAuthToken();
            return $token['access_token'] ?? null;
        } catch (\Exception $e) {
            Log::error('Failed to generate FCM access token', ['error' => $e->getMessage()]);
            return null;
        }
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
     * Send push notification to specific device token using V1 API
     */
    protected function send(
        string $deviceToken,
        string $title,
        string $body,
        array $data = []
    ): bool {
        if (!$this->projectId) {
            Log::error('FCM Project ID not configured or credentials file missing');
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }

        // Construct V1 Payload
        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data, // V1 data values must be strings
                // 'android' => [ // Optional: Android specific config
                //     'priority' => 'HIGH',
                //     'notification' => [
                //         'sound' => 'default'
                //     ]
                // ],
                // 'apns' => [ // Optional: iOS specific config
                //     'payload' => [
                //         'aps' => [
                //             'sound' => 'default'
                //         ]
                //     ]
                // ]
            ]
        ];

        // Ensure all data values are strings (Requirement for FCM V1)
        array_walk($payload['message']['data'], function (&$value) {
            if (!is_string($value)) {
                $value = (string) $value;
            }
        });

        $url = sprintf($this->fcmUrlPattern, $this->projectId);

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200) {
                Log::info('Push notification sent successfully (V1)', ['user_token' => substr($deviceToken, 0, 10) . '...']);
                return true;
            } else {
                Log::error('FCM V1 Send Error', ['http_code' => $httpCode, 'response' => $response, 'curl_error' => $error]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Curl exception in FCM send', ['error' => $e->getMessage()]);
            return false;
        }
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
