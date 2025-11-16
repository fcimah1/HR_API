<?php

namespace App\Services;

use App\Repository\Interface\LeaveRepositoryInterface;
use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Leave\CreateLeaveApplicationDTO;
use App\DTOs\Leave\UpdateLeaveApplicationDTO;
use App\DTOs\Leave\LeaveApplicationResponseDTO;
use App\DTOs\Leave\LeaveAdjustmentFilterDTO;
use App\DTOs\Leave\CreateLeaveAdjustmentDTO;
use App\DTOs\Leave\UpdateLeaveAdjustmentDTO;
use App\DTOs\Leave\LeaveAdjustmentResponseDTO;
use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\Http\Requests\Leave\ApproveLeaveApplicationRequest;
use App\Models\LeaveApplication;
use App\Models\LeaveAdjustment;
use App\Models\User;
use App\Services\SimplePermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LeaveService
{
    protected $leaveRepository;
    protected $permissionService;   

    public function __construct(
        LeaveRepositoryInterface $leaveRepository,
        SimplePermissionService $permissionService
    ) {
        $this->leaveRepository = $leaveRepository;
        $this->permissionService = $permissionService;
    }

    /**
     * Get paginated leave applications with filters and permission check
     */
    /**
     * Get paginated leave adjustments with permission check
     */
    public function getAdjustments(int $userId): array
    {
        $user = User::findOrFail($userId);
        
        // Create filter array instead of DTO directly
        $filterData = [];
        
        // Apply company filter based on user permissions
        if ($this->permissionService->isCompanyOwner($user)) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
        }
        // Create DTO from filter data
        $filters = LeaveAdjustmentFilterDTO::fromRequest($filterData);
        
        $adjustments = $this->leaveRepository->getPaginatedAdjustments($filters);
        
        return [
            'data' => $adjustments->items(),
            'pagination' => [
                'total' => $adjustments->total(),
                'per_page' => $adjustments->perPage(),
                'current_page' => $adjustments->currentPage(),
                'last_page' => $adjustments->lastPage(),
            ]
        ];
    }

    public function getPaginatedApplications(LeaveApplicationFilterDTO $filters, User $user): array
    {
        // إنشاء filters جديد بناءً على صلاحيات المستخدم
        $filterData = $filters->toArray();
        
        // إذا كان صاحب الشركة، يمكنه رؤية جميع طلبات شركته
        if ($this->permissionService->isCompanyOwner($user)) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
        } 
        // إذا كان موظف لديه صلاحية عرض جميع الطلبات
        elseif ($this->permissionService->checkPermission($user, 'leave.view.all')) {
            $filterData['company_id'] = $user->company_id;
        }
        // إذا كان موظف عادي، يرى طلباته الشخصية فقط
        else {
            $filterData['employee_id'] = $user->user_id;
            $filterData['company_id'] = $user->company_id;
        }
        
        // إنشاء DTO جديد مع البيانات المحدثة
        $updatedFilters = LeaveApplicationFilterDTO::fromRequest($filterData);

        $applications = $this->leaveRepository->getPaginatedApplications($updatedFilters);
        
        $applicationDTOs = collect($applications->items())->map(function ($application) {
            return LeaveApplicationResponseDTO::fromModel($application);
        });

        return [
            'data' => $applicationDTOs->map(fn($dto) => $dto->toArray())->toArray(),
            'pagination' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
                'from' => $applications->firstItem(),
                'to' => $applications->lastItem(),
                'has_more_pages' => $applications->hasMorePages(),
            ]
        ];
    }

    /**
     * Create a new leave application with permission check
     */
    public function createApplication(CreateLeaveApplicationDTO $dto): array
    {
        $application = $this->leaveRepository->createApplication($dto);
        return LeaveApplicationResponseDTO::fromModel($application)->toArray();
    }

    /**
     * Get leave application by ID with permission check
     * 
     * @param int $id Application ID
     * @param int|null $companyId Company ID (for company users/admins)
     * @param int|null $userId User ID (for regular employees)
     * @return array|null
     * @throws \Exception
     */
    public function getApplicationById(int $id, ?int $companyId = null, ?int $userId = null, ?User $user = null): ?array
    {
        $user = $user ?? Auth::user();
        
        if (is_null($companyId) && is_null($userId)) {
            throw new \InvalidArgumentException('يجب توفير معرف الشركة أو معرف المستخدم');
        }

        // Find application by company ID (for company users/admins)
        if ($companyId !== null) {
            $application = $this->leaveRepository->findApplicationInCompany($id, $companyId);
            
            if ($application) {
                return LeaveApplicationResponseDTO::fromModel($application)->toArray();
            }
        }
        
        // Find application by user ID (for regular employees)
        if ($userId !== null) {
            $application = $this->leaveRepository->findApplicationForEmployee($id, $userId);
            
            if ($application) {
                return LeaveApplicationResponseDTO::fromModel($application)->toArray();
            }
        }

        return null;
    }

    /**
     * Update leave application with permission check
     */
    // public function updateApplication(int $id, UpdateLeaveApplicationDTO $dto, User $user): ?LeaveApplicationResponseDTO
    // {
    //     \DB::beginTransaction();
    //     try {
    //         \Log::info('LeaveService::updateApplication started', [
    //             'application_id' => $id,
    //             'user_id' => $user->user_id,
    //             'updates' => array_keys(array_filter($dto->toArray()))
    //         ]);
            
    //         // Get effective company ID first
    //         $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            
    //         // Find application without loading relationships first
    //         $application = $this->leaveRepository->findApplicationInCompany($id, $effectiveCompanyId);
            
    //         if (!$application) {
    //             \Log::warning('Application not found', ['application_id' => $id, 'company_id' => $effectiveCompanyId]);
    //             \DB::rollBack();
    //             return null;
    //         }

    //         // Check permissions
    //         if ($application->employee_id !== $user->user_id) {
    //             \Log::warning('Permission denied - not owner', [
    //                 'application_employee_id' => $application->employee_id,
    //                 'current_user_id' => $user->user_id
    //             ]);
    //             \DB::rollBack();
    //             throw new \Exception('ليس لديك صلاحية لتعديل هذا الطلب');
    //         }

    //         // Check if application can be updated
    //         if ($application->status !== false) {
    //             \Log::warning('Cannot update - not pending', ['status' => $application->status]);
    //             \DB::rollBack();
    //             throw new \Exception('لا يمكن تعديل الطلب بعد المراجعة');
    //         }

    //         // Update application
    //         $updatedApplication = $this->leaveRepository->updateApplication($application, $dto);
            
    //         \DB::commit();
            
    //         \Log::info('Application updated successfully', [
    //             'application_id' => $updatedApplication->leave_id,
    //             'updates' => array_keys(array_filter($dto->toArray()))
    //         ]);
            
    //         return LeaveApplicationResponseDTO::fromModel($updatedApplication);
            
    //     } catch (\Exception $e) {
    //         \DB::rollBack();
    //         \Log::error('Error in LeaveService::updateApplication', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         throw $e;
    //     }
    // }   



    
    /**
     * Update leave application with permission check
     */
    public function update_Application(int $id, UpdateLeaveApplicationDTO $dto, User $user): ?array
    {
        try {
            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
    
            // البحث عن الطلب في نفس الشركة
            $application = $this->leaveRepository->findApplicationInCompany($id, $effectiveCompanyId);
            
            if (!$application) {
                return null;
            }

            // التحقق من صلاحية التعديل
            // الموظف يمكنه تعديل طلباته الخاصة فقط
            // المدير/الشركة يمكنهم تعديل أي طلب
            $isOwner = $application->employee_id === $user->user_id;
            $isManager = in_array($user->user_type, ['company', 'admin', 'hr', 'manager']);
     
            
            if (!$isOwner && !$isManager) {
                throw new \Exception('ليس لديك صلاحية لتعديل هذا الطلب');
            }

            // Check if application can be updated (only pending applications)
            if ($application->status !== false) {
                throw new \Exception('لا يمكن تعديل الطلب بعد المراجعة');
            }

            $updatedApplication = $this->leaveRepository->update_Application($application, $dto);
            
            return LeaveApplicationResponseDTO::fromModel($updatedApplication)->toArray();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Cancel leave application (mark as rejected)
     */
    public function cancelApplication(int $id, User $user): bool
    {
        // الحصول على معرف الشركة الفعلي
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        
        // البحث عن الطلب في نفس الشركة
        $application = $this->leaveRepository->findApplicationInCompany($id, $effectiveCompanyId);
        
        if (!$application) {
            return false;
        }

        // التحقق من الصلاحيات:
        // 1. الموظف صاحب الطلب يمكنه إلغاء طلباته المعلقة فقط
        // 2. المدير/الشركة يمكنهم إلغاء أي طلب (معلق أو موافق عليه)
        $isOwner = $application->employee_id === $user->user_id;
        $isManager = in_array($user->user_type, ['company', 'admin', 'hr', 'manager']);
        
        if (!$isOwner && !$isManager) {
            throw new \Exception('ليس لديك صلاحية لإلغاء هذا الطلب');
        }
        
        // الموظف العادي يمكنه إلغاء الطلبات المعلقة فقط
        if ($isOwner && !$isManager && $application->status !== false) {
            throw new \Exception('لا يمكن إلغاء الطلب بعد المراجعة');
        }

        // Mark as rejected (keeps record in database)
        $cancelReason = $isManager ? 'تم إلغاء الطلب من قبل الإدارة' : 'تم إلغاء الطلب من قبل الموظف';
        $this->leaveRepository->rejectApplication($application, $user->user_id, $cancelReason);
        
        return true;
    }

    /**
     * Delete leave application completely from database
     */
    public function deleteApplication(int $id, User $user): bool
    {
        // الحصول على معرف الشركة الفعلي
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        
        // البحث عن الطلب في نفس الشركة
        $application = $this->leaveRepository->findApplicationInCompany($id, $effectiveCompanyId);
        
        if (!$application) {
            return false;
        }

        // التحقق من الصلاحيات:
        // 1. الموظف صاحب الطلب يمكنه حذف طلباته المعلقة فقط
        // 2. المدير/الشركة يمكنهم حذف أي طلب (معلق أو موافق عليه)
        $isOwner = $application->employee_id === $user->user_id;
        $isManager = in_array($user->user_type, ['company', 'admin', 'hr', 'manager']);
        
        if (!$isOwner && !$isManager) {
            throw new \Exception('ليس لديك صلاحية لحذف هذا الطلب');
        }
        
        // الموظف العادي يمكنه حذف الطلبات المعلقة فقط
        if ($isOwner && !$isManager && $application->status !== false) {
            throw new \Exception('لا يمكن حذف الطلب بعد الموافقة عليه');
        }

        return $this->leaveRepository->deleteApplication($application);
    }

    /**
     * Approve leave application
     * 
     * @param int $id Leave application ID
     * @param ApproveLeaveApplicationRequest $request The request containing approval details
     * @return array|null
     * @throws \Exception
     */
    public function approveApplication(int $id, ApproveLeaveApplicationRequest $request): ?array
    {
        // Get the authenticated user from the request
        $user = $request->user();
        
        // Get the effective company ID for the user
        $companyId = $this->permissionService->getEffectiveCompanyId($user);
        
        // Find the application in the company
        $application = $this->leaveRepository->findApplicationInCompany($id, $companyId);
        
        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        // Check if the application is already processed
        if ($application->status !== false) {
            return response()->json([
                'success' => false,
                'message' => 'تم الموافقة على هذا الطلب مسبقاً أو تم رفضه'
            ], 422);
        }

        // Get the remarks from the validated request
        $validated = $request->validated();
        $remarks = $validated['remarks'] ?? null;

        // Approve the application
        $approvedApplication = $this->leaveRepository->approveApplication(
            $application, 
            $user->user_id, // approvedBy
            $remarks
        );
        
        return LeaveApplicationResponseDTO::fromModel($approvedApplication)->toArray();
    }

    /**
     * Reject leave application
     */
    public function rejectApplication(int $id, int $companyId, int $rejectedBy, string $reason): ?array
    {
        $application = $this->leaveRepository->findApplicationInCompany($id, $companyId);
        
        if (!$application) {
            return null;
        }

        if ($application->status !== false) {
            throw new \Exception('لا يمكن رفض طلب تم الموافقة عليه مسبقاً');
        }

        $rejectedApplication = $this->leaveRepository->rejectApplication($application, $rejectedBy, $reason);
        return LeaveApplicationResponseDTO::fromModel($rejectedApplication)->toArray();
    }

    /**
     * Get paginated leave adjustments
     */
    public function getPaginatedAdjustments(LeaveAdjustmentFilterDTO $filters): array
    {
        $adjustments = $this->leaveRepository->getPaginatedAdjustments($filters);

        return [
            'data' => $adjustments,
            'pagination' => [
                'current_page' => $adjustments->currentPage(),
                'last_page' => $adjustments->lastPage(),
                'per_page' => $adjustments->perPage(),
                'total' => $adjustments->total(),
                'from' => $adjustments->firstItem(),
                'to' => $adjustments->lastItem(),
                'has_more_pages' => $adjustments->hasMorePages(),
            ]
        ];
    }

    /**
     * Create leave adjustment
     */
    public function createAdjust(CreateLeaveAdjustmentDTO $data)
    {
        try {
            $adjustment = $this->leaveRepository->createAdjust($data);
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب التسوية بنجاح',
                'data' => $adjustment
            ], 201);
        } catch (\Exception $e) {
            Log::error('Create Adjustment Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء طلب التسوية',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve leave adjustment
     */
    public function approveAdjustment(int $id, int $companyId, int $approvedBy): LeaveAdjustment
    {
        $adjustment = $this->leaveRepository->findAdjustmentInCompany($id, $companyId);

        $approvedAdjustment = $this->leaveRepository->approveAdjustment($adjustment, $approvedBy);
        return $approvedAdjustment;
    }

    /**
     * Get leave statistics
     */
    public function getLeaveStatistics(int $companyId): array
    {
        return $this->leaveRepository->getLeaveStatistics($companyId);
    }

    /**
     * Get active leave types
     */
    public function getActiveLeaveTypes(int $companyId): array
    {
        $leaveTypes = $this->leaveRepository->getActiveLeaveTypes($companyId);
        
        return $leaveTypes->map(function($constant) {
            return [
                'leave_type_id' => $constant->constants_id,
                'leave_type_name' => $constant->leave_type_name,
                'leave_type_short_name' => $constant->leave_type_short_name,
                'leave_days' => $constant->leave_days,
                'leave_type_status' => $constant->leave_type_status,
                'company_id' => $constant->company_id,
            ];
        })->toArray();
    }

    /**
     * Create leave type
     */
    public function createLeaveType(CreateLeaveTypeDTO $dto): array
    {
        $leaveType = $this->leaveRepository->createLeaveType($dto);
        
        return [
            'leave_type_id' => $leaveType->constants_id,
            'leave_type_name' => $leaveType->leave_type_name,
            'leave_type_short_name' => $leaveType->leave_type_short_name,
            'leave_days' => $leaveType->leave_days,
            'leave_type_status' => $leaveType->leave_type_status,
            'company_id' => $leaveType->company_id,
        ];
    }

    /**
     * Update leave adjustment
     */
    public function updateAdjustment(int $id, UpdateLeaveAdjustmentDTO $dto, int $employeeId)
    {
        $adjustment = $this->leaveRepository->findAdjustmentForEmployee($id, $employeeId);

        $updatedAdjustment = $this->leaveRepository->updateAdjustment($adjustment, $dto);
    }

    /**
     * Cancel leave adjustment (mark as rejected)
     */
    public function cancelAdjustment(int $id, int $employeeId): bool
    {
        $adjustment = $this->leaveRepository->findAdjustmentForEmployee($id, $employeeId);
        
        if (!$adjustment) {
            return false;
        }

        // Mark as rejected (keeps record in database)
        $this->leaveRepository->cancelAdjustment($adjustment, $employeeId, 'تم إلغاء التسوية من قبل الموظف');
        
        return true;
    }

    /**
     * Delete leave adjustment completely from database
     */
    public function deleteAdjustment(int $id, int $employeeId): bool
    {
        $adjustment = $this->leaveRepository->findAdjustmentForEmployee($id, $employeeId);
        
        if (!$adjustment) {
            return false;
        }

        // Check if adjustment can be deleted (only pending adjustments)
        if ($adjustment->status !== LeaveAdjustment::STATUS_PENDING) {
            throw new \Exception('لا يمكن حذف التسوية بعد الموافقة عليها');
        }

        return $this->leaveRepository->deleteAdjustment($adjustment);
    }
}
