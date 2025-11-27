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

        return [
            'permissions' => $this->getUserPermissions(),
            'role_id' => $this->user_role_id,
            'role_name' => $this->staffRole?->role_name ?? null,
            'role_access' => $this->staffRole?->role_access ?? null,
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
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'staff_role', // Hide staff_role relationship from JSON responses
    ];

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

        return array_values($permissions); // Re-index array
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
}
