<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignatureDocument extends Model
{
    protected $table = 'ci_signature_documents';

    protected $primaryKey = 'document_id';

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'folder_id',
        'share_with_employees',
        'document_file',
        'document_name',
        'document_size',
        'signature_task',
        'created_at',
    ];

    /**
     * Get the company that owns the document.
     */
    public function company()
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }

    /**
     * Relationship with staff assignments
     */
    public function assignedStaff()
    {
        return $this->hasMany(StaffSignatureDocument::class, 'signature_file_id', 'document_id');
    }
}
