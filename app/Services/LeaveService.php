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
use App\Models\LeaveApplication;
use App\Models\LeaveAdjustment;
use App\Models\User;
use App\Services\SimplePermissionService;

class LeaveService
{
    public function __construct(
        private readonly LeaveRepositoryInterface $leaveRepository,
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * Get paginated leave applications with filters and permission check
     */
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
        
        $applicationDTOs = $applications->getCollection()->map(function ($application) {
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
    public function createApplication(CreateLeaveApplicationDTO $dto): LeaveApplicationResponseDTO
    {
        $application = $this->leaveRepository->createApplication($dto);
        return LeaveApplicationResponseDTO::fromModel($application);
    }

    /**
     * Get leave application by ID with permission check
     * 
     * @param int $id Application ID
     * @param int|null $companyId Company ID (for company users/admins)
     * @param int|null $userId User ID (for regular employees)
     * @return LeaveApplicationResponseDTO|null
     * @throws \Exception
     */
    public function getApplicationById(int $id, ?int $companyId = null, ?int $userId = null): ?LeaveApplicationResponseDTO
    {
        $user = auth()->user();
        
        if (is_null($companyId) && is_null($userId)) {
            throw new \InvalidArgumentException('يجب توفير معرف الشركة أو معرف المستخدم');
        }

        // Find application by company ID (for company users/admins)
        if ($companyId !== null) {
            $application = $this->leaveRepository->findApplicationInCompany($id, $companyId);
            
            if ($application) {
                return LeaveApplicationResponseDTO::fromModel($application);
            }
        }
        
        // Find application by user ID (for regular employees)
        if ($userId !== null) {
            $application = $this->leaveRepository->findApplicationForEmployee($id, $userId);
            
            if ($application) {
                return LeaveApplicationResponseDTO::fromModel($application);
            }
        }

        return null;
    }

    /**
     * Update leave application with permission check
     */
    public function updateApplication(int $id, UpdateLeaveApplicationDTO $dto, User $user): ?LeaveApplicationResponseDTO
    {
        // الحصول على معرف الشركة الفعلي
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        
        // البحث عن الطلب في نفس الشركة
        $application = $this->leaveRepository->findApplicationInCompany($id, $effectiveCompanyId);
        
        if (!$application) {
            return null;
        }

        // التحقق من صلاحية التعديل - يجب أن يكون الموظف صاحب الطلب
        if ($application->employee_id !== $user->user_id) {
            throw new \Exception('ليس لديك صلاحية لتعديل هذا الطلب');
        }

        // Check if application can be updated (only pending applications)
        if ($application->status !== false) {
            throw new \Exception('لا يمكن تعديل الطلب بعد المراجعة');
        }

        $updatedApplication = $this->leaveRepository->updateApplication($application, $dto);
        return LeaveApplicationResponseDTO::fromModel($updatedApplication);
    }

    /**
     * Cancel leave application (mark as rejected)
     */
    public function cancelApplication(int $id, int $employeeId): bool
    {
        $application = $this->leaveRepository->findApplicationForEmployee($id, $employeeId);
        
        if (!$application) {
            return false;
        }

        // Check if application can be cancelled (only pending applications)
        if ($application->status !== false) {
            throw new \Exception('لا يمكن إلغاء الطلب بعد الموافقة عليه');
        }

        // Mark as rejected (keeps record in database)
        $this->leaveRepository->rejectApplication($application, $employeeId, 'تم إلغاء الطلب من قبل الموظف');
        
        return true;
    }

    /**
     * Delete leave application completely from database
     */
    public function deleteApplication(int $id, int $employeeId): bool
    {
        $application = $this->leaveRepository->findApplicationForEmployee($id, $employeeId);
        
        if (!$application) {
            return false;
        }

        // Check if application can be deleted (only pending applications)
        if ($application->status !== false) {
            throw new \Exception('لا يمكن حذف الطلب بعد الموافقة عليه');
        }

        return $this->leaveRepository->deleteApplication($application);
    }

    /**
     * Approve leave application
     */
    public function approveApplication(int $id, int $companyId, int $approvedBy, ?string $remarks = null): ?LeaveApplicationResponseDTO
    {
        $application = $this->leaveRepository->findApplicationInCompany($id, $companyId);
        
        if (!$application) {
            return null;
        }

        if ($application->status !== false) {
            throw new \Exception('تم الموافقة على هذا الطلب مسبقاً أو تم رفضه');
        }

        $approvedApplication = $this->leaveRepository->approveApplication($application, $approvedBy, $remarks);
        return LeaveApplicationResponseDTO::fromModel($approvedApplication);
    }

    /**
     * Reject leave application
     */
    public function rejectApplication(int $id, int $companyId, int $rejectedBy, string $reason): ?LeaveApplicationResponseDTO
    {
        $application = $this->leaveRepository->findApplicationInCompany($id, $companyId);
        
        if (!$application) {
            return null;
        }

        if ($application->status !== false) {
            throw new \Exception('لا يمكن رفض طلب تم الموافقة عليه مسبقاً');
        }

        $rejectedApplication = $this->leaveRepository->rejectApplication($application, $rejectedBy, $reason);
        return LeaveApplicationResponseDTO::fromModel($rejectedApplication);
    }

    /**
     * Get paginated leave adjustments
     */
    public function getPaginatedAdjustments(LeaveAdjustmentFilterDTO $filters): array
    {
        $adjustments = $this->leaveRepository->getPaginatedAdjustments($filters);

        return [
            'data' => $adjustments->items(),
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
    public function createAdjustment(CreateLeaveAdjustmentDTO $dto): LeaveAdjustment
    {
        return $this->leaveRepository->createAdjustment($dto);
    }

    /**
     * Approve leave adjustment
     */
    public function approveAdjustment(int $id, int $companyId, int $approvedBy): ?LeaveAdjustment
    {
        $adjustment = $this->leaveRepository->findAdjustmentInCompany($id, $companyId);
        
        if (!$adjustment) {
            return null;
        }

        if ($adjustment->status !== \App\Models\LeaveAdjustment::STATUS_PENDING) {
            throw new \Exception('لا يمكن الموافقة على هذا الطلب');
        }

        return $this->leaveRepository->approveAdjustment($adjustment, $approvedBy);
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
    public function createLeaveType(int $companyId, string $name, ?string $shortName, int $days): array
    {
        $leaveType = $this->leaveRepository->createLeaveType($companyId, $name, $shortName, $days);
        
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
    public function updateAdjustment(int $id, UpdateLeaveAdjustmentDTO $dto, int $employeeId): ?LeaveAdjustmentResponseDTO
    {
        $adjustment = $this->leaveRepository->findAdjustmentForEmployee($id, $employeeId);
        
        if (!$adjustment) {
            return null;
        }

        // Check if adjustment can be updated (only pending adjustments)
        if ($adjustment->status !== LeaveAdjustment::STATUS_PENDING) {
            throw new \Exception('لا يمكن تعديل التسوية بعد المراجعة');
        }

        $updatedAdjustment = $this->leaveRepository->updateAdjustment($adjustment, $dto);
        return LeaveAdjustmentResponseDTO::fromModel($updatedAdjustment);
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
