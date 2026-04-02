<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAccount extends Model
{
    use HasFactory;

    protected $table = 'ci_employee_accounts';
    protected $primaryKey = 'account_id';
    const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'account_name',
        'created_at',
    ];
}
