<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemDocument extends Model
{
    use HasFactory;

    protected $table = 'ci_system_documents';
    protected $primaryKey = 'document_id';

    // Since created_at is varchar(200) and not a standard timestamp column
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'department_id',
        'document_name',
        'document_type',
        'document_file',
        'created_at',
    ];

    /**
     * Relationship with Company (User with type company)
     */
    public function company()
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }

    /**
     * Relationship with Department
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }
}
