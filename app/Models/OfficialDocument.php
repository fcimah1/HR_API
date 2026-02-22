<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialDocument extends Model
{
    protected $table = 'ci_official_documents';

    protected $primaryKey = 'document_id';

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'license_name',
        'document_type',
        'license_no',
        'expiry_date',
        'document_file',
        'created_at',
    ];

    /**
     * Relationship with Company (User)
     */
    public function company()
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }
}
