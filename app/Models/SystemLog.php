<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    use HasFactory;

    protected $table = 'ci_erp_system_logs';

    protected $fillable = [
        'level',
        'message',
        'context',
        'user_id',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'request',
        'response',
        'user_name',
    ];

    protected $casts = [
        'context' => 'array',
        'request' => 'array',
        'response' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
