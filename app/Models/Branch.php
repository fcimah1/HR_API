<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    use HasFactory;

    /**
     * Disable automated timestamps as the table lacks updated_at.
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'ci_branchs';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'branch_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'branch_name',
        'description',
        'company_id',
        'coordinates',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'branch_id' => 'integer',
        'company_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the manager of this branch.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id', 'user_id');
    }

    /**
     * Get the user details that belong to this branch.
     */
    public function userDetails(): HasMany
    {
        return $this->hasMany(UserDetails::class, 'branch_id', 'branch_id');
    }

    /**
     * Get the company that owns the branch.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Get the name attribute (alias for branch_name).
     */
    public function getNameAttribute()
    {
        return $this->branch_name;
    }

    /**
     * Get formatted coordinates safely (avoiding binary data issues)
     */
    public function getFormattedCoordinatesAttribute(): ?string
    {
        // Prioritize coordinates_text (ST_AsText output from Repository)
        $coords = $this->attributes['coordinates_text'] ?? $this->coordinates;

        if (empty($coords)) {
            return null;
        }

        if (is_string($coords)) {
            // Check if it's binary junk (if ST_AsText failed or wasn't used)
            // Only return null if it's definitely binary and NOT a WKT string
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\xFF]/', $coords)) {
                // If ST_AsText was used, coordinates_text should NOT be binary
                return null;
            }

            // If it's a simple "lat,lng" string
            if (preg_match('/^-?\d+\.?\d*,\s*-?\d+\.?\d*$/', $coords)) {
                return $coords;
            }

            // Handle WKT POINT(lng lat)
            if (preg_match('/POINT\(([^ ]+) ([^ ]+)\)/i', $coords, $matches)) {
                return $matches[2] . ',' . $matches[1];
            }

            // Handle Degenerate POLYGON((lng lat, lng lat, ...)) back to lat,lng
            if (preg_match('/POLYGON\(\(([^,]+),([^,]+),([^,]+),([^,]+)\)\)/i', $coords, $matches)) {
                $p1 = trim($matches[1]);
                $p2 = trim($matches[2]);
                if ($p1 === $p2) { // All points are the same
                    $parts = explode(' ', $p1);
                    if (count($parts) === 2) {
                        return $parts[1] . ',' . $parts[0]; // lat,lng
                    }
                }
            }

            return $coords; // Return WKT (POLYGON, etc.) as is
        }

        return null;
    }

    /**
     * Get latitude from coordinates
     */
    public function getLatitudeAttribute(): ?string
    {
        $coords = $this->formatted_coordinates;
        if (empty($coords)) {
            return null;
        }

        $parts = explode(',', $coords);
        return isset($parts[0]) ? trim($parts[0]) : null;
    }

    /**
     * Get longitude from coordinates
     */
    public function getLongitudeAttribute(): ?string
    {
        $coords = $this->formatted_coordinates;
        if (empty($coords)) {
            return null;
        }

        $parts = explode(',', $coords);
        return isset($parts[1]) ? trim($parts[1]) : null;
    }
}
