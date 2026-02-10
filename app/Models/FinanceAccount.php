<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceAccount extends Model
{
    use HasFactory;

    protected $table = 'ci_finance_accounts';
    protected $primaryKey = 'account_id';
    const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'account_name',
        'account_balance',
        'account_opening_balance',
        'account_number',
        'branch_code',
        'bank_branch',
    ];

    protected $casts = [
        'account_balance' => 'decimal:2',
        'account_opening_balance' => 'decimal:2',
    ];

    // Relationships
    public function transactions()
    {
        return $this->hasMany(FinanceTransaction::class, 'account_id');
    }
}
