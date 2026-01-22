<?php

namespace App\Services;

use App\Models\User;

class PushNotificationService
{
    protected string $projectId = 'hr-system-app-7e407';
    protected string $credentialsPath;

    public function __construct()
    {
        $this->credentialsPath = storage_path('app/firebase_credentials.json');
    }

    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        $user = User::where('user_id', $userId)->first();
        if (!$user || !$user->device_token) {
            return false;
        }

        $messagePayload = [
            'token' => $user->device_token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ]
        ];

        // Add data payload if provided
        if (!empty($data)) {
            // FCM requires data values to be strings
            $stringData = array_map(function ($value) {
                return (string) $value;
            }, $data);
            $messagePayload['data'] = $stringData;
        }

        $payload = ['message' => $messagePayload];

        $accessToken = $this->getSimpleAccessToken();
        if (!$accessToken) return false;

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false  // 🔥 للـ test
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    public function sendSubmissionPush(array $staffIds, string $moduleOption, string $submitterName, string $keyId): void
    {
        $title = 'طلب جديد';
        $body = "قام {$submitterName} بتقديم طلب {$moduleOption} رقم #{$keyId}";

        foreach ($staffIds as $userId) {
            $this->sendToUser((int)$userId, $title, $body);
        }
    }

    public function sendApprovalPush(array $staffIds, string $moduleOption, string $status, string $keyId): void
    {
        $statusText = $status === 'approved' ? 'الموافقة على' : ($status === 'rejected' ? 'رفض' : 'تحديث حالة');
        $title = 'تحديث حالة الطلب';
        $body = "تم {$statusText} طلب {$moduleOption} رقم #{$keyId}";

        foreach ($staffIds as $userId) {
            $this->sendToUser((int)$userId, $title, $body);
        }
    }

    protected function getSimpleAccessToken(): ?string
    {
        $credentials = json_decode(file_get_contents($this->credentialsPath), true);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->createJWT($credentials)
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    private function createJWT(array $credentials): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $now = time();
        $claim = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ]));

        $jwt = "$header.$claim";
        openssl_sign($jwt, $signature, $credentials['private_key'], 'sha256WithRSAEncryption');
        return $jwt . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
