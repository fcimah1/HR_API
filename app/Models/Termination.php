<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Termination extends Model
{
    use HasFactory;

    protected $table = 'ci_terminations';
    protected $primaryKey = 'termination_id';

    // Disable updated_at since legacy table only has created_at
    public $timestamps = true;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'employee_id',
        'notice_date',
        'termination_date',
        'document_file',
        'is_signed',
        'signed_file',
        'signed_date',
        'reason',
        'added_by',
        'status',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }
}
