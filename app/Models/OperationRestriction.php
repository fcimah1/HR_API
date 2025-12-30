<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationRestriction extends Model
{
    use HasFactory;

    protected $table = 'ci_operation_restrictions';
    protected $primaryKey = 'restriction_id';

    protected $fillable = [
        'company_id',
        'user_id',
        'restricted_operations',
        'created_by',
    ];

    /**
     * Get the restricted operations as an array.
     *
     * @return array
     */
    public function getRestrictedOperationsAttribute(): array
    {
        if (empty($this->attributes['restricted_operations'])) {
            return [];
        }

        return array_map('trim', explode(',', $this->attributes['restricted_operations']));
    }

    /**
     * Set the restricted operations from an array.
     *
     * @param array|string $value
     * @return void
     */
    public function setRestrictedOperationsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['restricted_operations'] = implode(',', $value);
        } else {
            $this->attributes['restricted_operations'] = $value;
        }
    }
}
