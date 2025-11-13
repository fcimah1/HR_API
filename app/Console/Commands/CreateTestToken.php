<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CreateTestToken extends Command
{
    protected $signature = 'token:create {user_id}';
    protected $description = 'Create API token for testing';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("المستخدم غير موجود: {$userId}");
            return 1;
        }

        $token = $user->createToken('test-token')->plainTextToken;
        
        $this->info("🔑 Token للمستخدم {$user->first_name} {$user->last_name} (ID: {$userId}):");
        $this->info("=====================================");
        $this->line($token);
        
        return 0;
    }
}
