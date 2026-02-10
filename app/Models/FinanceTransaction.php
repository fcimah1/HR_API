<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceTransaction extends Model
{
    use HasFactory;

    protected $table = 'ci_finance_transactions';
    protected $primaryKey = 'transaction_id';
    const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'company_id',
        'staff_id',
        'transaction_date',
        'transaction_type', // deposit, expense, transfer
        'entity_id',
        'entity_type',
        'entity_category_id',
        'description',
        'amount',
        'dr_cr', // dr, cr
        'payment_method_id',
        'reference',
        'attachment_file',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the attachment file with directory prepended for display
     */
    protected function getAttachmentFileAttribute($value)
    {
        return $value ? 'transactions/' . $value : null;
    }

    // Relationships
    public function account()
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function category()
    {
        return $this->belongsTo(ErpConstant::class, 'entity_category_id', 'constants_id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id', 'id'); // Assuming User model has ID 'id' or 'user_id'? User model usually uses 'id'.
    }
}
