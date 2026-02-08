<?php

namespace App\Services;

use App\DTOs\CustodyClearance\CustodyFilterDTO;
use App\DTOs\CustodyClearance\CustodyClearanceFilterDTO;
use App\DTOs\CustodyClearance\CreateCustodyClearanceDTO;
use App\DTOs\CustodyClearance\ApproveCustodyClearanceDTO;
use App\DTOs\CustodyClearance\CustodyResponseDTO;
use App\DTOs\CustodyClearance\CustodyClearanceResponseDTO;
use App\Enums\StringStatusEnum;
use App\Models\User;
use App\Repository\Interface\CustodyClearanceRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustodyClearanceService
{
    public function __construct(
        protected CustodyClearanceRepositoryInterface $clearanceRepository,
        protected SimplePermissionService $permissionService,
        protected NotificationService $notificationService,
        protected ApprovalService $approvalService,
    ) {}

    /**
     * الحصول على العهد للموظف أو تابعيه
     */
    public function getCustodiesForEmployee(User $user, CustodyFilterDTO $filters): mixed
    {
        $userType = strtolower(trim($user->user_type ?? ''));
        $companyId = $user->company_id;

        if ($userType === 'company') {
            $companyId = $user->user_id;
        }

        // Build filters with company
        $filterData = [
            'company_id' => $companyId,
            'employee_id' => $filters->employeeId,
            'employee_ids' => $filters->employeeIds,
            'search' => $filters->search,
            'status' => $filters->status,
            'page' => $filters->page,
            'per_page' => $filters->perPage,
        ];

        // If no specific employee, check permissions
        if ($filters->employeeId === null) {
            if ($userType === 'company') {
                // Company can see all
            } elseif ($userType === 'staff') {
                // Get allowed employees based on hierarchy
                $allowedEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId, true);
                $allowedEmployeeIds = array_column($allowedEmployees, 'user_id');

                $filterData['employee_ids'] = $allowedEmployeeIds;
            }
        } else {
            // Verify permission to view specific employee
            $targetEmployee = User::find($filters->employeeId);
            if (!$targetEmployee) {
                Log::info('CustodyClearanceService::getCustodiesForEmployee - Employee not found', [
                    'employee_id' => $filters->employeeId,
                    'message' => 'الموظف غير موجود',
                ]);
                throw new \Exception('الموظف غير موجود');
            }

            if (!$this->permissionService->canViewEmployeeRequests($user, $targetEmployee)) {
                Log::info('CustodyClearanceService::getCustodiesForEmployee - Permission denied', [
                    'employee_id' => $filters->employeeId,
                    'message' => 'ليس لديك صلاحية لعرض عهد هذا الموظف',
                ]);
                throw new \Exception('ليس لديك صلاحية لعرض عهد هذا الموظف');
            }
        }

        $result = $this->clearanceRepository->getCustodiesForEmployee(
            CustodyFilterDTO::fromRequest($filterData)
        );

        if ($result instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            return $result->through(function ($asset) {
                return CustodyResponseDTO::fromModel($asset)->toArray();
            });
        }

        return $result->map(function ($asset) {
            return CustodyResponseDTO::fromModel($asset)->toArray();
        });
    }

    /**
     * الحصول على قائمة طلبات الإخلاء
     */
    public function getPaginatedClearances(CustodyClearanceFilterDTO $filters, User $user): mixed
    {
        $companyId = $user->company_id;
        $userType = strtolower(trim($user->user_type ?? ''));

        // Build filters
        $filterData = [
            'company_id' => $companyId,
            'employee_id' => $filters->employeeId,
            'employee_ids' => $filters->employeeIds,
            'status' => $filters->status,
            'clearance_type' => $filters->clearanceType,
            'from_date' => $filters->fromDate,
            'to_date' => $filters->toDate,
            'search' => $filters->search,
            'page' => $filters->page,
            'per_page' => $filters->perPage,
        ];

        if ($userType === 'company') {
            $filterData['company_id'] = $user->user_id;
        } elseif ($userType === 'staff') {
            // Get allowed employees based on hierarchy
            $allowedEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId, true);
            $allowedEmployeeIds = array_column($allowedEmployees, 'user_id');

            $filterData['employee_ids'] = $allowedEmployeeIds;
        }

        $result = $this->clearanceRepository->getPaginatedClearances(
            CustodyClearanceFilterDTO::fromRequest($filterData),
            $user
        );

        if ($result instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            return $result->through(function ($clearance) {
                return CustodyClearanceResponseDTO::fromModel($clearance)->toArray();
            });
        }

        return $result->map(function ($clearance) {
            return CustodyClearanceResponseDTO::fromModel($clearance)->toArray();
        });
    }

    /**
     * الحصول على طلب إخلاء بواسطة المعرف
     */
    public function getClearanceById(int $id, User $user): CustodyClearanceResponseDTO
    {
        $companyId = $user->company_id;
        $userType = strtolower(trim($user->user_type ?? ''));

        if ($userType === 'company') {
            $companyId = $user->user_id;
        }

        $clearance = $this->clearanceRepository->findClearanceById($id, $companyId);

        if (!$clearance) {
            Log::info('CustodyClearanceService::getClearanceById - Clearance not found', [
                'clearance_id' => $id,
                'message' => 'طلب الإخلاء غير موجود',
            ]);
            throw new \Exception('طلب الإخلاء غير موجود');
        }

        // Check permission
        if ($userType === 'staff') {
            $targetEmployee = User::find($clearance->employee_id);
            if (!$this->permissionService->canViewEmployeeRequests($user, $targetEmployee)) {
                Log::info('CustodyClearanceService::getClearanceById - Permission denied', [
                    'clearance_id' => $id,
                    'message' => 'ليس لديك صلاحية لعرض هذا الطلب',
                ]);
                throw new \Exception('ليس لديك صلاحية لعرض هذا الطلب');
            }
        }

        return CustodyClearanceResponseDTO::fromModel($clearance);
    }

    /**
     * إنشاء طلب إخلاء جديد
     */
    public function createClearance(CreateCustodyClearanceDTO $dto): CustodyClearanceResponseDTO
    {
        return DB::transaction(function () use ($dto) {
            // Verify that the creator can create for this employee
            $creator = User::find($dto->createdBy);
            if ($creator->user_id !== $dto->employeeId) {
                // Not creating for self, check hierarchy
                $allowedEmployees = $this->permissionService->getEmployeesByHierarchy($creator->user_id, $dto->companyId, false);
                $allowedEmployeeIds = array_column($allowedEmployees, 'user_id');

                if (!in_array($dto->employeeId, $allowedEmployeeIds)) {
                    Log::warning('CustodyClearanceService::createClearance - Unauthorized creation attempt', [
                        'creator_id' => $creator->user_id,
                        'target_employee_id' => $dto->employeeId,
                        'message' => 'ليس لديك صلاحية لإنشاء طلب إخلاء طرف لهذا الموظف',
                    ]);
                    throw new \Exception('ليس لديك صلاحية لإنشاء طلب إخلاء طرف لهذا الموظف');
                }
            }

            // Start of validation
            // Get valid custodies for this employee
            $validCustodyAssets = $this->clearanceRepository->getAllCustodiesForEmployee($dto->employeeId);
            $validAssetIds = array_column($validCustodyAssets, 'assets_id');

            // Get custodies for employee
            $assetIds = $dto->assetIds;
            if (empty($assetIds)) {
                // If no specific assets requested, use all valid assets
                $assetIds = $validAssetIds;
            } else {
                // Validate that requested assets belong to the employee
                $invalidAssetIds = array_diff($assetIds, $validAssetIds);
                if (!empty($invalidAssetIds)) {
                    Log::warning('CustodyClearanceService::createClearance - Invalid assets requested', [
                        'employee_id' => $dto->employeeId,
                        'invalid_assets' => $invalidAssetIds,
                        'message' => 'بعض الأصول المحددة غير مسجلة كعهد لهذا الموظف',
                    ]);
                    throw new \Exception('بعض الأصول المحددة غير مسجلة كعهد لهذا الموظف');
                }
            }

            if (empty($assetIds)) {
                Log::info('CustodyClearanceService::createClearance - No custodies found', [
                    'employee_id' => $dto->employeeId,
                    'message' => 'لا توجد عهد مسجلة لهذا الموظف',
                ]);
                throw new \Exception('لا توجد عهد مسجلة لهذا الموظف');
            }

            // Create clearance
            $clearance = $this->clearanceRepository->createClearance($dto);

            // Add items
            $this->clearanceRepository->addClearanceItems($clearance->clearance_id, $assetIds);

            // Reload with items
            $clearance->refresh();
            $clearance->load(['employee', 'items.asset', 'approvals.staff']);

            // Send submission notification
            $this->notificationService->sendSubmissionNotification(
                'custody_clearance_settings',
                (string) $clearance->clearance_id,
                $dto->companyId,
                StringStatusEnum::PENDING->value,
                $dto->employeeId
            );

            // Send email to first approver
            // Email will be sent via notification service

            Log::info('CustodyClearanceService::createClearance completed', [
                'clearance_id' => $clearance->clearance_id,
                'employee_id' => $dto->employeeId,
                'asset_count' => count($assetIds),
            ]);

            return CustodyClearanceResponseDTO::fromModel($clearance);
        });
    }

    /**
     * الموافقة أو الرفض
     */
    public function approveOrRejectClearance(int $id, ApproveCustodyClearanceDTO $dto): CustodyClearanceResponseDTO
    {
        return DB::transaction(function () use ($id, $dto) {
            $user = Auth::user();
            $companyId = $user->company_id;
            $userType = strtolower(trim($user->user_type ?? ''));

            if ($userType === 'company') {
                $companyId = $user->user_id;
            }

            $clearance = $this->clearanceRepository->findClearanceById($id, $companyId);

            if (!$clearance) {
                Log::info('CustodyClearanceService::approveOrRejectClearance - Clearance not found', [
                    'clearance_id' => $id,
                    'message' => 'طلب الإخلاء غير موجود',
                ]);
                throw new \Exception('طلب الإخلاء غير موجود');
            }

            if ($clearance->status !== 'pending') {
                Log::info('CustodyClearanceService::approveOrRejectClearance - Clearance not found', [
                    'clearance_id' => $id,
                    'message' => 'لا يمكن معالجة طلب تم البت فيه مسبقاً',
                ]);
                throw new \Exception('لا يمكن معالجة طلب تم البت فيه مسبقاً');
            }

            // Strict hierarchy check for approval
            if ($userType !== 'company') {
                $employee = User::find($clearance->employee_id);
                if (!$employee || !$this->permissionService->canApproveEmployeeRequests($user, $employee)) {
                    Log::warning('CustodyClearanceService::approveOrRejectClearance - Permission denied', [
                        'clearance_id' => $id,
                        'approver_id' => $user->user_id,
                        'target_employee_id' => $clearance->employee_id,
                        'message' => 'ليس لديك صلاحية لمعالجة طلب إخلاء هذا الموظف',
                    ]);
                    throw new \Exception('ليس لديك صلاحية لمعالجة طلب إخلاء هذا الموظف');
                }
            }

            // Company user can approve/reject directly
            if ($userType === 'company') {
                if ($dto->action === 'approve') {
                    $processedClearance = $this->clearanceRepository->approveClearance(
                        $clearance,
                        $dto->processedBy,
                        $dto->remarks
                    );

                    // Record approval
                    $this->approvalService->recordApproval(
                        $clearance->clearance_id,
                        $dto->processedBy,
                        1,
                        1,
                        'custody_clearance_settings',
                        $companyId,
                        $clearance->employee_id
                    );

                    // Send approval notification
                    $this->notificationService->sendApprovalNotification(
                        'custody_clearance_settings',
                        (string) $clearance->clearance_id,
                        $companyId,
                        StringStatusEnum::APPROVED->value,
                        $dto->processedBy,
                        null,
                        $clearance->employee_id
                    );



                    return CustodyClearanceResponseDTO::fromModel($processedClearance);
                } else {
                    $processedClearance = $this->clearanceRepository->rejectClearance(
                        $clearance,
                        $dto->processedBy,
                        $dto->remarks
                    );

                    // Record rejection
                    $this->approvalService->recordApproval(
                        $clearance->clearance_id,
                        $dto->processedBy,
                        2,
                        2,
                        'custody_clearance_settings',
                        $companyId,
                        $clearance->employee_id
                    );

                    // Send rejection notification
                    $this->notificationService->sendApprovalNotification(
                        'custody_clearance_settings',
                        (string) $clearance->clearance_id,
                        $companyId,
                        StringStatusEnum::REJECTED->value,
                        $dto->processedBy,
                        null,
                        $clearance->employee_id
                    );



                    return CustodyClearanceResponseDTO::fromModel($processedClearance);
                }
            }

            // For staff users, verify approval levels
            $canApprove = $this->approvalService->canUserApprove(
                $dto->processedBy,
                $clearance->clearance_id,
                $clearance->employee_id,
                'custody_clearance_settings'
            );

            if (!$canApprove) {
                $denialInfo = $this->approvalService->getApprovalDenialReason(
                    $dto->processedBy,
                    $clearance->clearance_id,
                    $clearance->employee_id,
                    'custody_clearance_settings'
                );
                Log::info('CustodyClearanceService::approveOrRejectClearance - Approval denied', [
                    'clearance_id' => $id,
                    'message' => $denialInfo['message'],
                ]);
                throw new \Exception($denialInfo['message']);
            }

            if ($dto->action === 'approve') {
                // Check if this is the final approval
                $isFinal = $this->approvalService->isFinalApproval(
                    $clearance->clearance_id,
                    $clearance->employee_id,
                    'custody_clearance_settings'
                );

                if ($isFinal) {
                    // Final approval
                    $processedClearance = $this->clearanceRepository->approveClearance(
                        $clearance,
                        $dto->processedBy,
                        $dto->remarks
                    );

                    // Record final approval
                    $this->approvalService->recordApproval(
                        $clearance->clearance_id,
                        $dto->processedBy,
                        1,
                        1,
                        'custody_clearance_settings',
                        $companyId,
                        $clearance->employee_id
                    );

                    // Send approval notification
                    $this->notificationService->sendApprovalNotification(
                        'custody_clearance_settings',
                        (string) $clearance->clearance_id,
                        $companyId,
                        StringStatusEnum::APPROVED->value,
                        $dto->processedBy,
                        null,
                        $clearance->employee_id
                    );



                    Log::info('CustodyClearanceService::approveOrRejectClearance - Final approval', [
                        'clearance_id' => $id,
                        'approved_by' => $dto->processedBy,
                        'message' => 'تم الموافقة على طلب الإخلاء',
                    ]);

                    return CustodyClearanceResponseDTO::fromModel($processedClearance);
                } else {
                    // Intermediate approval
                    $this->approvalService->recordApproval(
                        $clearance->clearance_id,
                        $dto->processedBy,
                        1,
                        0,
                        'custody_clearance_settings',
                        $companyId,
                        $clearance->employee_id
                    );

                    // Send intermediate notification
                    $this->notificationService->sendApprovalNotification(
                        'custody_clearance_settings',
                        (string) $clearance->clearance_id,
                        $companyId,
                        StringStatusEnum::APPROVED->value,
                        $dto->processedBy,
                        1,
                        $clearance->employee_id
                    );

                    Log::info('CustodyClearanceService::approveOrRejectClearance - Intermediate approval', [
                        'clearance_id' => $id,
                        'approved_by' => $dto->processedBy,
                        'message' => 'تم الموافقة على طلب الإخلاء',
                    ]);

                    $clearance->refresh();
                    return CustodyClearanceResponseDTO::fromModel($clearance);
                }
            } else {
                // Rejection
                $processedClearance = $this->clearanceRepository->rejectClearance(
                    $clearance,
                    $dto->processedBy,
                    $dto->remarks
                );

                // Record rejection
                $this->approvalService->recordApproval(
                    $clearance->clearance_id,
                    $dto->processedBy,
                    2,
                    2,
                    'custody_clearance_settings',
                    $companyId,
                    $clearance->employee_id
                );

                // Send rejection notification
                $this->notificationService->sendApprovalNotification(
                    'custody_clearance_settings',
                    (string) $clearance->clearance_id,
                    $companyId,
                    StringStatusEnum::REJECTED->value,
                    $dto->processedBy,
                    null,
                    $clearance->employee_id
                );



                Log::info('CustodyClearanceService::approveOrRejectClearance - Rejected', [
                    'clearance_id' => $id,
                    'rejected_by' => $dto->processedBy,
                    'message' => 'Rejected',
                ]);

                return CustodyClearanceResponseDTO::fromModel($processedClearance);
            }
        });
    }

    /**
     * الحصول على معرفات الموظفين التابعين
     */
    protected function getSubordinateEmployeeIds(User $manager): array
    {
        return User::where('company_id', $manager->company_id)
            ->where('user_type', 'staff')
            ->where('is_active', 1)
            ->whereHas('user_details', function ($query) use ($manager) {
                $query->where('reporting_manager', $manager->user_id);
            })
            ->pluck('user_id')
            ->toArray();
    }
}
