<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $table = 'ci_events';
    protected $primaryKey = 'event_id';

    // created_at is a string in the schema, and updated_at is not present
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'employee_id', // Comma-separated string of IDs
        'event_title',
        'event_date',
        'event_time',
        'event_note',
        'event_color',
        'is_show_calendar',
        'created_at',
    ];

    /**
     * Get the company that owns the event.
     */
    public function company()
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }

    /**
     * Since employee_id is a comma-separated string, we could add a helper attribute 
     * but we'll handle the logic in the Service/Resource layer.
     */
}
