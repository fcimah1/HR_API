<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffSignatureDocument extends Model
{
    protected $table = 'ci_staff_signature_documents';

    protected $primaryKey = 'document_id';

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'staff_id',
        'signature_file_id',
        'signature_task',
        'is_signed',
        'signed_file',
        'signed_date',
        'created_at',
    ];

    /**
     * Get the main signature document.
     */
    public function signatureDocument()
    {
        return $this->belongsTo(SignatureDocument::class, 'signature_file_id', 'document_id');
    }

    /**
     * Get the employee assigned to this document.
     */
    public function employee()
    {
        return $this->belongsTo(User::class, 'staff_id', 'user_id');
    }
}
