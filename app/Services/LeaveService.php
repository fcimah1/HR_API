<?php

namespace App\Services;

use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Leave\CreateLeaveApplicationDTO;
use App\DTOs\Leave\UpdateLeaveApplicationDTO;
use App\DTOs\Leave\LeaveApplicationResponseDTO;
use App\DTOs\Leave\LeaveAdjustmentFilterDTO;
use App\DTOs\Leave\CreateLeaveAdjustmentDTO;
use App\DTOs\Leave\UpdateLeaveAdjustmentDTO;
use App\DTOs\Leave\CreateLeaveSettlementDTO;
use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\DTOs\Leave\UpdateLeaveTypeDTO;
use App\Http\Requests\Leave\ApproveLeaveApplicationRequest;
use App\Models\LeaveAdjustment;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Repository\Interface\LeaveRepositoryInterface;
use App\Repository\Interface\LeaveTypeRepositoryInterface;
use App\Services\SimplePermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveService
{
    protected $leaveRepository;
    protected $leaveTypeRepository;
    protected $permissionService;

    public function __construct(
        LeaveRepositoryInterface $leaveRepository,
        LeaveTypeRepositoryInterface $leaveTypeRepository,
        SimplePermissionService $permissionService
    ) {
        $this->leaveRepository = $leaveRepository;
        $this->leaveTypeRepository = $leaveTypeRepository;
        $this->permissionService = $permissionService;
    }

    /**
     * Get paginated leave applications with filters and permission check
     */



    public function getPaginatedApplications(LeaveApplicationFilterDTO $filters, User $user): array
    {
        // إنشاء filters جديد بناءً على صلاحيات المستخدم
        $filterData = $filters->toArray();

        // التحقق من نوع المستخدم (company أو staff فقط)
        if ($user->user_type == 'company') {
            // مدير الشركة: يرى جميع طلبات شركته
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
        } else {
            // موظف (staff): يرى طلباته + طلبات الموظفين التابعين له
            $subordinateIds = $this->getSubordinateEmployeeIds($user->user_id);

            if (!empty($subordinateIds)) {
                // لديه موظفين تابعين: طلباته + طلبات التابعين
                $subordinateIds[] = $user->user_id; // إضافة نفسه
                $filterData['employee_ids'] = $subordinateIds;
                $filterData['company_id'] = $user->company_id;
            } else {
                // ليس لديه موظفين تابعين: طلباته فقط
                $filterData['employee_id'] = $user->user_id;
                $filterData['company_id'] = $user->company_id;
            }
        }

        // إنشاء DTO جديد مع البيانات المحدثة
        $updatedFilters = LeaveApplicationFilterDTO::fromRequest($filterData);

        $applications = $this->leaveRepository->getPaginatedApplications($updatedFilters);

        return [
            'data' => $applications->items(),
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
     * Get all subordinate employee IDs using recursive reporting structure
     * 
     * @param int $managerId
     * @return array
     */
    private function getSubordinateEmployeeIds(int $managerId): array
    {
        $db = \Illuminate\Support\Facades\DB::connection()->getPdo();

        // Recursive query to get all subordinates (same as CodeIgniter)
        $sql = "SELECT user_id
                FROM (SELECT * FROM `ci_erp_users_details` ORDER BY user_id) reporting_manager,
                     (SELECT @pv := :manager_id) initialisation
                WHERE FIND_IN_SET(reporting_manager, @pv)
                AND LENGTH(@pv := CONCAT(@pv, ',', user_id))";

        $stmt = $db->prepare($sql);
        $stmt->execute(['manager_id' => $managerId]);
        $results = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('intval', $results);
    }

    /**
     * Create a new leave application with permission check
     */
    public function createApplication(CreateLeaveApplicationDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            Log::info('LeaveService::createApplication - Transaction started', [
                'employee_id' => $dto->employeeId,
                'leave_type_id' => $dto->leaveTypeId
            ]);

            // حساب الساعات المطلوبة للإجازة
            if (!is_null($dto->leaveHours) && $dto->leaveHours !== '') {
                $requestedHours = (float) $dto->leaveHours * $dto->getDurationInDays();
            } else {
                $days = $dto->getDurationInDays();
                $requestedHours = $days * 8.0; // 8 ساعات لليوم الواحد
            }

            // جلب الرصيد المتاح لنوع الإجازة
            $availableBalance = $this->getAvailableLeaveBalance(
                $dto->employeeId,
                $dto->leaveTypeId,
                $dto->companyId
            );

            Log::info('LeaveService::createApplication:availableBalance', [
                'availableBalance' => $availableBalance,
                'requestedHours' => $requestedHours,
            ]);

            // إذا كانت الإجازة المطلوبة أكبر من الرصيد المتاح نرفض الطلب
            if ($requestedHours > $availableBalance) {
                throw new \Exception(
                    'ساعات الإجازة المطلوبة (' . $requestedHours . ' ساعة) أكبر من الرصيد المتاح (' . $availableBalance . ' ساعة) لهذا النوع.'
                );
            }

            $application = $this->leaveRepository->createApplication($dto);

            Log::info('LeaveService::createApplication - Transaction committed', [
                'application_id' => $application->leave_id
            ]);

            return LeaveApplicationResponseDTO::fromModel($application)->toArray();
        });
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
    /**
     * Update leave application with permission check
     */
    public function update_Application(int $id, UpdateLeaveApplicationDTO $dto, User $user): ?array
    {
        return DB::transaction(function () use ($id, $dto, $user) {
            Log::info('LeaveService::update_Application - Transaction started', [
                'application_id' => $id,
                'user_id' => $user->user_id
            ]);

            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // البحث عن الطلب في نفس الشركة
            $application = $this->leaveRepository->findApplicationInCompany($id, $effectiveCompanyId);

            if (!$application) {
                throw new \Exception('الطلب غير موجود');
            }

            // التحقق من صلاحية التعديل
            $isOwner = $application->employee_id === $user->user_id;

            if (!$isOwner) {
                throw new \Exception('ليس لديك صلاحية لتعديل هذا الطلب');
            }

            // Check if application can be updated (only pending applications)
            if ($application->status !== LeaveApplication::STATUS_PENDING) {
                throw new \Exception('لا يمكن تعديل الطلب بعد المراجعة');
            }

            $updatedApplication = $this->leaveRepository->update_Application($application, $dto);

            Log::info('LeaveService::update_Application - Transaction committed', [
                'application_id' => $updatedApplication->leave_id
            ]);

            return LeaveApplicationResponseDTO::fromModel($updatedApplication)->toArray();
        });
    }

    /**
     * Cancel leave application (mark as rejected)
     */
    /**
     * Cancel leave application (mark as rejected)
     */
    public function cancelApplication(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {
            Log::info('LeaveService::cancelApplication - Transaction started', [
                'application_id' => $id,
                'user_id' => $user->user_id
            ]);

            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // البحث عن الطلب في نفس الشركة
            $application = $this->leaveRepository->findApplicationInCompany($id, $effectiveCompanyId);

            if (!$application) {
                throw new \Exception('الطلب غير موجود');
            }

            // التحقق من الصلاحيات:
            // 1. الموظف صاحب الطلب يمكنه إلغاء طلباته المعلقة فقط
            // 2. المدير/الشركة يمكنهم إلغاء أي طلب (معلق أو موافق عليه)
            $isOwner = $application->employee_id === $user->user_id;

            if (!$isOwner) {
                throw new \Exception('ليس لديك صلاحية لإلغاء هذا الطلب');
            }

            // الموظف العادي يمكنه إلغاء الطلبات المعلقة فقط
            if ($isOwner && $application->status !== \App\Models\LeaveApplication::STATUS_PENDING) {
                throw new \Exception('لا يمكن إلغاء الطلب بعد المراجعة');
            }

            // Mark as rejected (keeps record in database)
            $cancelReason = 'تم إلغاء الطلب من قبل الموظف';
            $this->leaveRepository->rejectApplication($application, $user->user_id, $cancelReason);

            Log::info('LeaveService::cancelApplication - Transaction committed', [
                'application_id' => $id
            ]);

            return true;
        });
    }

    /**
     * Approve leave application
     * 
     * @param int $id Leave application ID
     * @param ApproveLeaveApplicationRequest $request The request containing approval details
     * @return array|null
     * @throws \Exception
     */
    public function approveApplication(int $id, ApproveLeaveApplicationRequest $request): object
    {
        return DB::transaction(function () use ($id, $request) {
            Log::info('LeaveService::approveApplication - Transaction started', [
                'application_id' => $id
            ]);

            // Get the authenticated user from the request
            $user = $request->user();

            // Get the effective company ID for the user
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            // Find the application in the company
            $application = $this->leaveRepository->findApplicationInCompany($id, $companyId);

            if (!$application) {
                throw new \Exception('الطلب غير موجود');
            }

            // Check if the application is already processed
            if ($application->status !== \App\Models\LeaveApplication::STATUS_PENDING) {
                throw new \Exception('تم الموافقة على هذا الطلب مسبقاً أو تم رفضه');
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

            Log::info('LeaveService::approveApplication - Transaction committed', [
                'application_id' => $approvedApplication->leave_id,
                'approved_by' => $user->user_id
            ]);

            return LeaveApplicationResponseDTO::fromModel($approvedApplication);
        });
    }

    /**
     * Reject leave application
     */
    public function rejectApplication(int $id, int $companyId, int $rejectedBy, string $reason): ?array
    {
        return DB::transaction(function () use ($id, $companyId, $rejectedBy, $reason) {
            Log::info('LeaveService::rejectApplication - Transaction started', [
                'application_id' => $id,
                'rejected_by' => $rejectedBy
            ]);

            $application = $this->leaveRepository->findApplicationInCompany($id, $companyId);

            if (!$application) {
                throw new \Exception('الطلب غير موجود');
            }

            if ($application->status !== \App\Models\LeaveApplication::STATUS_PENDING) {
                throw new \Exception('لا يمكن رفض طلب تم الموافقة عليه مسبقاً');
            }

            $rejectedApplication = $this->leaveRepository->rejectApplication($application, $rejectedBy, $reason);

            Log::info('LeaveService::rejectApplication - Transaction committed', [
                'application_id' => $rejectedApplication->leave_id
            ]);

            return LeaveApplicationResponseDTO::fromModel($rejectedApplication)->toArray();
        });
    }

    /**
     * Get leave statistics
     */
    public function getLeaveStatistics(int $companyId): array
    {
        return $this->leaveRepository->getLeaveStatistics($companyId,);
    }



    /**
     * Get available leave balance for an employee
     *
     * @param int $employeeId
     * @param int $leaveTypeId
     * @param int $companyId
     * @return float
     */
    public function getAvailableLeaveBalance(int $employeeId, int $leaveTypeId, int $companyId): float
    {
        // 1. Get total granted leave
        $totalGranted = $this->leaveRepository->getTotalGrantedLeave(
            $employeeId,
            $leaveTypeId,
            $companyId
        );

        // 2. Get total used leave
        $totalUsed = $this->leaveRepository->getTotalUsedLeave(
            $employeeId,
            $leaveTypeId,
            $companyId
        );

        // 3. Calculate available balance
        return max(0, $totalGranted - $totalUsed);
    }

    /**
     * Get detailed leave summary (hours & days) for an employee
     */
    public function getDetailedLeaveSummary(int $employeeId, int $companyId, ?int $leaveTypeId = null): array
    {
        $hoursPerDay = 8.0;

        // Get active leave types for the company
        $leaveTypesCollection = $this->leaveTypeRepository->getActiveLeaveTypes($companyId);

        // Convert collection to array format expected by the rest of the method
        $leaveTypes = $leaveTypesCollection->map(function ($constant) {
            return [
                'leave_type_id' => $constant->constants_id,
                'leave_type_name' => $constant->leave_type_name,
                'leave_type_short_name' => $constant->leave_type_short_name,
                'leave_days' => $constant->leave_days,
                'leave_type_status' => $constant->leave_type_status,
                'company_id' => $constant->company_id,
            ];
        })->toArray();

        if ($leaveTypeId !== null) {
            $leaveTypes = array_values(array_filter($leaveTypes, function (array $type) use ($leaveTypeId) {
                return (int) ($type['leave_type_id'] ?? 0) === $leaveTypeId;
            }));
        }

        $items = [];

        $totals = [
            'granted_hours' => 0.0,
            'used_hours' => 0.0,
            'pending_hours' => 0.0,
            'adjustment_hours' => 0.0,
            'balance_hours' => 0.0,
            'remaining_hours' => 0.0,
        ];

        $currentYear = (int) date('Y');

        foreach ($leaveTypes as $type) {
            $typeId = (int) ($type['leave_type_id'] ?? 0);
            if (! $typeId) {
                continue;
            }

            $granted = $this->leaveRepository->getTotalGrantedLeave($employeeId, $typeId, $companyId);
            $used = $this->leaveRepository->getTotalUsedLeave($employeeId, $typeId, $companyId);
            $pending = $this->leaveRepository->getPendingLeaveHours($employeeId, $typeId, $companyId);
            $adjustments = $this->leaveRepository->getTotalAdjustmentHours($employeeId, $typeId, $companyId);

            $entitled = $granted + $adjustments;
            $balance = $entitled - $used;
            $remaining = $balance - $pending;

            $items[] = [
                'leave_type_id' => $typeId,
                'leave_type_name' => $type['leave_type_name'] ?? null,
                'leave_type_short_name' => $type['leave_type_short_name'] ?? null,
                'year' => $currentYear,

                // بالساعات
                'granted_hours' => (float) $granted,
                'used_hours' => (float) $used,
                'pending_hours' => (float) $pending,
                'adjustment_hours' => (float) $adjustments,
                'entitled_hours' => (float) $entitled,
                'balance_hours' => (float) $balance,
                'remaining_hours' => (float) $remaining,

                // بالأيام
                'granted_days' => (float) round($granted / $hoursPerDay, 2),
                'used_days' => (float) round($used / $hoursPerDay, 2),
                'pending_days' => (float) round($pending / $hoursPerDay, 2),
                'adjustment_days' => (float) round($adjustments / $hoursPerDay, 2),
                'entitled_days' => (float) round($entitled / $hoursPerDay, 2),
                'balance_days' => (float) round($balance / $hoursPerDay, 2),
                'remaining_days' => (float) round($remaining / $hoursPerDay, 2),
            ];

            $totals['granted_hours'] += $granted;
            $totals['used_hours'] += $used;
            $totals['pending_hours'] += $pending;
            $totals['adjustment_hours'] += $adjustments;
            $totals['balance_hours'] += $balance;
            $totals['remaining_hours'] += $remaining;
        }

        $totalsWithDays = [
            'granted_hours' => (float) $totals['granted_hours'],
            'used_hours' => (float) $totals['used_hours'],
            'pending_hours' => (float) $totals['pending_hours'],
            'adjustment_hours' => (float) $totals['adjustment_hours'],
            'balance_hours' => (float) $totals['balance_hours'],
            'remaining_hours' => (float) $totals['remaining_hours'],

            'granted_days' => (float) round($totals['granted_hours'] / $hoursPerDay, 2),
            'used_days' => (float) round($totals['used_hours'] / $hoursPerDay, 2),
            'pending_days' => (float) round($totals['pending_hours'] / $hoursPerDay, 2),
            'adjustment_days' => (float) round($totals['adjustment_hours'] / $hoursPerDay, 2),
            'balance_days' => (float) round($totals['balance_hours'] / $hoursPerDay, 2),
            'remaining_days' => (float) round($totals['remaining_hours'] / $hoursPerDay, 2),
        ];

        return [
            'employee_id' => $employeeId,
            'company_id' => $companyId,
            'hours_per_day' => $hoursPerDay,
            'total' => $totalsWithDays,
            'items' => $items,
        ];
    }

    /**
     * Get monthly leave statistics for an employee
     * Returns detailed monthly breakdown for each leave type
     * 
     * @param int $employeeId
     * @param int $companyId
     * @return array
     */
    public function getMonthlyLeaveStatistics(int $employeeId, int $companyId): array
    {
        $currentYear = (int) date('Y');

        // Get active leave types for the company
        $leaveTypesCollection = $this->leaveTypeRepository->getActiveLeaveTypes($companyId);

        // Convert collection to array
        $leaveTypes = $leaveTypesCollection->map(function ($constant) {
            return [
                'leave_type_id' => $constant->constants_id,
                'leave_type_name' => $constant->leave_type_name,
                'leave_type_short_name' => $constant->leave_type_short_name,
                'leave_days' => $constant->leave_days,
                'field_one' => $constant->field_one,
            ];
        })->toArray();

        // Get employee details for assigned_hours
        $employee = \App\Models\User::find($employeeId);
        $assignedHours = [];

        if ($employee) {
            $details = $employee->user_details()->first();
            if ($details && !empty($details->assigned_hours)) {
                $assignedHours = @unserialize($details->assigned_hours);
                if (!is_array($assignedHours)) {
                    $assignedHours = [];
                }
            }
        }

        $result = [];

        foreach ($leaveTypes as $type) {
            $typeId = (int) ($type['leave_type_id'] ?? 0);
            if (!$typeId) {
                continue;
            }

            // Get assigned hours for this leave type
            $typeAssignedHours = $assignedHours[$typeId] ?? 0;

            // Check if leave accrual is enabled for this type
            $fieldOne = $type['field_one'] ?? '';
            $leaveOptions = @unserialize($fieldOne);
            $enableLeaveAccrual = false;

            if (is_array($leaveOptions) && isset($leaveOptions['enable_leave_accrual'])) {
                $enableLeaveAccrual = $leaveOptions['enable_leave_accrual'] == 1;
            }

            // Get monthly data
            $monthlyGranted = $this->leaveRepository->getMonthlyGrantedHours($employeeId, $typeId, $companyId);
            $monthlyUsed = $this->leaveRepository->getMonthlyUsedHours($employeeId, $typeId, $companyId, $currentYear);

            // Calculate monthly remaining
            $monthlyBreakdown = [];
            $months = [
                1 => 'Jan',
                2 => 'Feb',
                3 => 'Mar',
                4 => 'Apr',
                5 => 'May',
                6 => 'Jun',
                7 => 'Jul',
                8 => 'Aug',
                9 => 'Sep',
                10 => 'Oct',
                11 => 'Nov',
                12 => 'Dec'
            ];

            foreach ($months as $monthNum => $monthName) {
                $granted = $monthlyGranted[$monthNum] ?? 0.0;
                $used = $monthlyUsed[$monthNum] ?? 0.0;
                $remaining = $granted - $used;

                $monthlyBreakdown[$monthNum] = [
                    'month_name' => $monthName,
                    'granted' => (float) $granted,
                    'used' => (float) $used,
                    'remaining' => (float) $remaining,
                ];
            }

            $result[] = [
                'leave_type_id' => $typeId,
                'leave_type_name' => $type['leave_type_name'] ?? null,
                'leave_type_short_name' => $type['leave_type_short_name'] ?? null,
                'assigned_hours' => (float) $typeAssignedHours,
                'enable_leave_accrual' => $enableLeaveAccrual,
                'monthly_breakdown' => $monthlyBreakdown,
            ];
        }

        return [
            'employee_id' => $employeeId,
            'company_id' => $companyId,
            'year' => $currentYear,
            'leave_types' => $result,
        ];
    }
}
