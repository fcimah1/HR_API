<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{


    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    public $timestamps = false;


    protected $table = 'ci_erp_users';

    protected $primaryKey = 'user_id';

    // get all fields of user roles table
    protected $allowedFields = ['user_id', 'user_role_id', 'user_type', 'company_id', 'first_name', 'last_name', 'email', 'username', 'password', 'company_name', 'trading_name', 'registration_no', 'government_tax', 'company_type_id', 'profile_photo', 'contact_number', 'gender', 'address_1', 'address_2', 'city', 'state', 'zipcode', 'country', 'last_login_date', 'last_logout_date', 'last_login_ip', 'is_logged_in', 'is_active', 'kiosk_code', 'fiscal_date', 'created_at'];


    /**
     * Send permissions with the user details when logged in
     */
    public function sendPermissionsWithUserDetails(): array
    {
        // Load staffRole if not already loaded
        if (!$this->relationLoaded('staffRole')) {
            $this->load('staffRole');
        }

        // Load user_details with department, designation and branch if not already loaded
        if (!$this->relationLoaded('user_details')) {
            $this->load(['user_details.department', 'user_details.designation', 'user_details.branch']);
        }

        return [
            'permissions' => $this->getUserPermissions(),
            'role_id' => $this->user_role_id,
            'role_name' => $this->staffRole?->role_name ?? null,
            'role_access' => $this->staffRole?->role_access ?? null,
            'department_id' => $this->user_details?->department_id ?? null,
            'department_name' => $this->user_details?->department?->department_name ?? null,
            'designation_id' => $this->user_details?->designation_id ?? null,
            'designation_name' => $this->user_details?->designation?->designation_name ?? null,
            'hierarchy_level'  => $this->user_details?->designation?->hierarchy_level ?? null,
            'branch_id' => $this->user_details?->branch_id ?? null,
            'branch_name' => $this->user_details?->branch?->branch_name ?? null,
        ];
    }


    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_role_id',
        'user_type',
        'company_id',
        'first_name',
        'last_name',
        'email',
        'username',
        'password',
        'company_name',
        'trading_name',
        'registration_no',
        'government_tax',
        'company_type_id',
        'profile_photo',
        'contact_number',
        'gender',
        'address_1',
        'address_2',
        'city',
        'state',
        'zipcode',
        'country',
        'last_login_date',
        'last_logout_date',
        'last_login_ip',
        'is_logged_in',
        'is_active',
        'kiosk_code',
        'created_at',
        'fiscal_date',
        'device_token',
        'kiosk_code',
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'staffRole', // Hide staffRole relationship from JSON responses
        'staff_role', // Alias for backward compatibility
    ];

    /**
     * Convert the model instance to an array.
     * Overridden to ensure all strings are UTF-8 encoded.
     */
    public function toArray()
    {
        $attributes = parent::toArray();

        array_walk_recursive($attributes, function (&$value) {
            if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
        });

        return $attributes;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_logged_in' => 'boolean',
            'user_role_id' => 'integer',
            'company_id' => 'integer',
            'country' => 'integer',
        ];
    }

    /**
     * Get the user details
     */
    public function user_details(): HasOne
    {
        return $this->hasOne(UserDetails::class, 'user_id', 'user_id');
    }

    public function getShiftNameAttribute(): ?string
    {
        // Check if eager loaded through user_details.officeShift
        // Need to ensure officeShift relation exists in UserDetails
        if ($this->relationLoaded('user_details')) {
            if ($this->user_details->relationLoaded('officeShift')) {
                return $this->user_details->officeShift->shift_name ?? null;
            }
            // Fallback: fetch if ID exists
            if ($this->user_details && $this->user_details->office_shift_id) {
                $shift = OfficeShift::find($this->user_details->office_shift_id);
                return $shift?->shift_name;
            }
        }
        return null; // Or 'وردية العمل الإداري' as default if preferred, but handled in ReportService
    }

    /**
     * Get branches for company (when user_type = 'company')
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'company_id', 'user_id');
    }

    /**
     * Get the company that this user belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }

    /**
     * Get user department
     */

    public function user_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'user_id', 'user_id');
    }

    /**
     * Alias for user_details (for backward compatibility)
     */
    public function details(): HasOne
    {
        return $this->user_details();
    }


    /**
     * Get user permissions from staff role
     */
    public function getUserPermissions(): array
    {
        // Load staffRole if not already loaded
        if (!$this->relationLoaded('staffRole')) {
            $this->load('staffRole');
        }

        // Check if staffRole exists
        if (!$this->staffRole) {
            return [];
        }

        // Verify that the role belongs to the same company (security check)
        if ($this->staffRole->company_id !== $this->company_id) {
            Log::warning('User role company mismatch', [
                'user_id' => $this->user_id,
                'user_company_id' => $this->company_id,
                'role_company_id' => $this->staffRole->company_id,
            ]);
            return [];
        }

        // Get permissions from role_resources
        $permissions = array_filter(explode(',', $this->staffRole->role_resources ?? ''));

        return array_values(array_unique($permissions)); // Re-index array and remove duplicates
    }


    /**
     * العلاقة مع الدور من نفس الشركة
     * Relationship with staff role from the same company
     * Note: We can't use whereColumn with eager loading, so we match by role_id only
     * Company matching is validated at the application level
     */
    public function staffRole(): BelongsTo
    {
        return $this->belongsTo(StaffRole::class, 'user_role_id', 'role_id');
    }

    /**
     * Get the attendance records for the user.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'employee_id', 'user_id');
    }

    /**
     * Get hierarchy level from designation
     */
    public function getHierarchyLevelAttribute()
    {
        $level = $this->user_details?->designation?->hierarchy_level;

        // If no hierarchy level found and user is staff, assign default level 5 (lowest)
        if ($level === null && $this->user_type === 'staff') {
            return 5;
        }

        return $level;
    }

    /**
     * Get department ID from user details
     */
    public function getDepartmentIdAttribute()
    {
        return $this->user_details?->department_id;
    }

    /**
     * الحصول على مستخدمي نفس الشركة
     */
    public function scopeSameCompany(Builder $query): Builder
    {
        return $query->where('company_name', $this->company_name);
    }

    /**
     * فلترة المستخدمين حسب اسم الشركة
     */
    public function scopeByCompany(Builder $query, string $companyName): Builder
    {
        return $query->where('company_name', $companyName);
    }

    /**
     * فلترة المستخدمين النشطين فقط
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    /**
     * فلترة المستخدمين حسب النوع
     */
    public function scopeByType(Builder $query, string $userType): Builder
    {
        return $query->where('user_type', $userType);
    }

    /**
     * التحقق من الصلاحية
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->staffRole) {
            return false;
        }

        return $this->staffRole->hasPermission($permission);
    }

    /**
     * التحقق من وجود أي من الصلاحيات
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if (!$this->staffRole) {
            return false;
        }

        return $this->staffRole->hasAnyPermission($permissions);
    }

    /**
     * التحقق من وجود جميع الصلاحيات
     */
    public function hasAllPermissions(array $permissions): bool
    {
        if (!$this->staffRole) {
            return false;
        }

        return $this->staffRole->hasAllPermissions($permissions);
    }

    /**
     * التحقق من الدور
     */
    public function hasRole(string $roleName): bool
    {
        return $this->staffRole && $this->staffRole->role_name === $roleName;
    }

    /**
     * التحقق من مستوى الوصول
     */
    public function hasAccessLevel(int $accessLevel): bool
    {
        return $this->staffRole && $this->staffRole->role_access === $accessLevel;
    }

    /**
     * التحقق من أن المستخدم من نفس الشركة
     */
    public function isFromSameCompany(User $otherUser): bool
    {
        return $this->company_name === $otherUser->company_name;
    }

    /**
     * التحقق من أن البيانات تنتمي لنفس الشركة
     */
    public function canAccessCompanyData($data): bool
    {
        if (is_object($data) && isset($data->company_name)) {
            return $this->company_name === $data->company_name;
        }

        if (is_array($data) && isset($data['company_name'])) {
            return $this->company_name === $data['company_name'];
        }

        return false;
    }

    /**
     * الحصول على اسم الشركة
     */
    public function getCompanyName(): string
    {
        return $this->company_name ?? '';
    }

    /**
     * الحصول على رقم الشركة
     */
    public function getCompanyId(): int
    {
        return $this->company_id ?? 0;
    }

    /**
     * الحصول على الاسم الكامل
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    // get full name by user id
    public static function getFullNameById(int $userId): string
    {
        return User::find($userId)->getFullNameAttribute();
    }

    /**
     * التحقق من أن المستخدم مدير
     */
    public function isManager(): bool
    {
        return $this->hasAccessLevel(1) || $this->hasAccessLevel(2) || $this->hasAccessLevel(3);
    }

    /**
     * التحقق من أن المستخدم موظف عادي
     */
    public function isEmployee(): bool
    {
        return $this->hasAccessLevel(4);
    }

    /**
     * الحصول على جميع الصلاحيات
     */
    public function getAllPermissions(): array
    {
        if (!$this->staffRole) {
            return [];
        }

        return $this->staffRole->permissions;
    }

    /**
     * التحقق من إمكانية الوصول لمورد محدد
     */
    public function canAccess(string $resource): bool
    {
        if (!$this->staffRole) {
            return false;
        }

        return $this->staffRole->canAccess($resource);
    }

    /**
     * الحصول على اسم الدور
     */
    public function getRoleName(): string
    {
        return $this->staffRole->role_name ?? 'بدون دور';
    }

    /**
     * الحصول على مستوى الوصول
     */
    public function getAccessLevel(): int
    {
        return $this->staffRole->role_access ?? 0;
    }

    /**
     * Get hierarchy level of the user from their designation
     */
    public function getHierarchyLevel(): ?int
    {
        // Get hierarchy level from designation through user_details
        $level = $this->user_details?->designation?->hierarchy_level;

        // If no hierarchy level found and user is staff, assign default level 5 (lowest)
        if ($level === null && $this->user_type === 'staff') {
            return 5;
        }

        return $level;
    }

    /**
     * Check if user can make requests for another employee
     * Based on hierarchy level, department, and reporting manager
     * 
     * @param User $employee The employee to check permission for
     * @return bool
     */
    public function canMakeRequestFor(User $employee): bool
    {
        // If requester has no hierarchy level, deny
        if ($this->getHierarchyLevel() === null) {
            return false;
        }

        // If target employee has no hierarchy level, deny
        if ($employee->getHierarchyLevel() === null) {
            return false;
        }

        // Must be from same company
        if ($this->company_id !== $employee->company_id) {
            return false;
        }

        // Load user details if not loaded
        if (!$this->relationLoaded('user_details')) {
            $this->load('user_details');
        }
        if (!$employee->relationLoaded('user_details')) {
            $employee->load('user_details');
        }

        // Must be from same department
        if (!$this->user_details || !$employee->user_details) {
            return false;
        }

        if ($this->user_details->department_id !== $employee->user_details->department_id) {
            return false;
        }

        // Check if requester is the reporting manager OR has higher hierarchy level
        $isReportingManager = $this->user_id === $employee->user_details->reporting_manager;
        $hasHigherHierarchy = $this->getHierarchyLevel() < $employee->getHierarchyLevel();

        return $isReportingManager || $hasHigherHierarchy;
    }

    /**
     * Get employees that this user can make requests for
     * Filters by same company, same department, and lower hierarchy level
     * 
     * @return Builder
     */
    public function getSubordinatesQuery(): Builder
    {
        if ($this->getHierarchyLevel() === null) {
            return self::query()->whereRaw('1 = 0'); // Return empty query
        }

        // Load user details if not loaded
        if (!$this->relationLoaded('user_details')) {
            $this->load('user_details');
        }

        if (!$this->user_details) {
            return self::query()->whereRaw('1 = 0'); // Return empty query
        }

        return self::query()
            ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
            ->join('ci_designations', 'ci_erp_users_details.designation_id', '=', 'ci_designations.designation_id')
            ->where('ci_erp_users.company_id', $this->company_id)
            ->where('ci_erp_users_details.department_id', $this->user_details->department_id)
            ->where(function ($query) {
                // Either reporting to this user OR has lower hierarchy level
                $query->where('ci_erp_users_details.reporting_manager', $this->user_id)
                    ->orWhere(function ($q) {
                        $q->where('ci_designations.hierarchy_level', '>', $this->getHierarchyLevel())
                            ->whereNotNull('ci_designations.hierarchy_level');
                    });
            })
            ->where('ci_erp_users.is_active', 1)
            ->select('ci_erp_users.*');
    }
}
