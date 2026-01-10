<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PushNotificationService;

class TestFcm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:test {token : The FCM device token}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test FCM notification to a specific token';

    /**
     * Execute the console command.
     */
    public function handle(PushNotificationService $pushService)
    {
        $token = $this->argument('token');

        $this->info("Sending test notification to: " . substr($token, 0, 10) . "...");

        // Use reflection to access protected `send` method for raw token testing
        // or ensure sendToUsers is used if we had a user ID.
        // Since we want to test with a raw token, we'll try to use the `send` method manually via reflection
        // OR better, let's expose a public method for testing or just mock a send.

        // Actually, PushNotificationService::send is protected. 
        // Let's modify the service to allow sending to a raw token via a public method for testing?
        // No, let's stick to reflection for this test command to avoid polluting the service interface,
        // OR simply make `send` public if it makes sense. 
        // Making `send` public is fine. But let's use reflection here to be safe and quick.

        try {
            $method = new \ReflectionMethod(PushNotificationService::class, 'send');
            $method->setAccessible(true);

            $result = $method->invoke(
                $pushService,
                $token,
                'Test Notification 🔔',
                'This is a test message from Laravel CLI using FCM V1',
                ['type' => 'cli_test']
            );

            if ($result) {
                $this->info('✅ Notification sent successfully!');
            } else {
                $this->error('❌ Failed to send notification. Check logs for details.');
            }
        } catch (\Exception $e) {
            $this->error('Exception: ' . $e->getMessage());
        }
    }
}
