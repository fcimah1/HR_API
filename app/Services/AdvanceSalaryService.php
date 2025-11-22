<?php

namespace App\Services;

use App\Repository\Interface\AdvanceSalaryRepositoryInterface;
use App\DTOs\AdvanceSalary\AdvanceSalaryFilterDTO;
use App\DTOs\AdvanceSalary\CreateAdvanceSalaryDTO;
use App\DTOs\AdvanceSalary\UpdateAdvanceSalaryDTO;
use App\DTOs\AdvanceSalary\AdvanceSalaryResponseDTO;
use App\Models\AdvanceSalary;
use App\Models\User;
use App\Services\SimplePermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvanceSalaryService
{
    public function __construct(
        private readonly AdvanceSalaryRepositoryInterface $advanceSalaryRepository,
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * Get paginated advance salary/loan requests with filters and permission check
     */
    public function getPaginatedAdvances(AdvanceSalaryFilterDTO $filters, User $user): array
    {
        // Create new filters based on user permissions
        $filterData = $filters->toArray();
        
        // Check what types the user has permission for
        $canViewAdvance = $this->permissionService->checkPermissionWithFallback($user, 'hradvance_salary', 'advance_salary1');
        $canViewLoan = $this->permissionService->checkPermissionWithFallback($user, 'hrloan', 'loan1');
        $canCreateAdvance = $this->permissionService->checkPermissionWithFallback($user, 'hradvance_salary', 'advance_salary2');
        $canCreateLoan = $this->permissionService->checkPermissionWithFallback($user, 'hrloan', 'loan2');
        
        // Determine allowed types based on permissions
        $allowedTypes = [];
        if ($canViewAdvance || $canCreateAdvance) {
            $allowedTypes[] = 'advance';
        }
        if ($canViewLoan || $canCreateLoan) {
            $allowedTypes[] = 'loan';
        }
        
        if (empty($allowedTypes)) {
            throw new \Exception('ليس لديك صلاحية لعرض طلبات السلفة أو القروض');
        }
        
        // If user only has permission for one type and no type filter is specified, filter by that type
        $requestedType = $filterData['type'] ?? null;
        if (count($allowedTypes) === 1 && $requestedType === null) {
            $filterData['type'] = $allowedTypes[0];
        } elseif ($requestedType !== null && !in_array($requestedType, $allowedTypes)) {
            // User requested a type they don't have permission for
            throw new \Exception('ليس لديك صلاحية لعرض هذا النوع من الطلبات');
        }
        
        // If company owner, can see all requests in their company
        if ($this->permissionService->isCompanyOwner($user)) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
        } 
        // Check if user has permission to view company-wide (not just their own)
        elseif ($canViewAdvance || $canViewLoan) {
            $filterData['company_id'] = $user->company_id;
        }
        // Regular employee can only see their own requests
        else {
            $filterData['employee_id'] = $user->user_id;
            $filterData['company_id'] = $user->company_id;
        }
        
        // Create new DTO with updated data
        $updatedFilters = AdvanceSalaryFilterDTO::fromRequest($filterData);

        $advances = $this->advanceSalaryRepository->getPaginatedAdvances($updatedFilters);
        
        // Map to DTOs and filter results to ensure user only sees types they have permission for
        // (This is a safety check - the query should already be filtered by type)
        $advanceDTOs = collect($advances->items())
            ->map(function ($advance) {
                return AdvanceSalaryResponseDTO::fromModel($advance);
            })
            ->filter(function ($dto) use ($allowedTypes) {
                return in_array($dto->salaryType, $allowedTypes);
            });

        return [
            'data' => $advanceDTOs->map(fn($dto) => $dto->toArray())->toArray(),
            'pagination' => [
                'current_page' => $advances->currentPage(),
                'last_page' => $advances->lastPage(),
                'per_page' => $advances->perPage(),
                'total' => $advances->total(), // Use query total (type filter applied in query)
                'from' => $advances->firstItem(),
                'to' => $advances->lastItem(),
                'has_more_pages' => $advances->hasMorePages(),
            ]
        ];
    }

    /**
     * Check if user has permission to view advances/loans
     * 
     * @param User $user
     * @param string|null $type 'loan' or 'advance' or null for both
     * @return bool
     */
    private function hasViewPermission(User $user, ?string $type): bool
    {
        if ($type === 'loan') {
            return $this->permissionService->checkPermissionWithFallback($user, 'hrloan', 'loan1');
        } elseif ($type === 'advance') {
            return $this->permissionService->checkPermissionWithFallback($user, 'hradvance_salary', 'advance_salary1');
        } else {
            // Check both permissions - user needs at least one
            $canViewAdvance = $this->permissionService->checkPermissionWithFallback($user, 'hradvance_salary', 'advance_salary1');
            $canViewLoan = $this->permissionService->checkPermissionWithFallback($user, 'hrloan', 'loan1');
            return $canViewAdvance || $canViewLoan;
        }
    }

    /**
     * Create a new advance salary/loan request with permission check
     */
    public function createAdvance(CreateAdvanceSalaryDTO $dto, User $user): AdvanceSalaryResponseDTO
    {
        return \DB::transaction(function () use ($dto, $user) {
            try {
                Log::info('AdvanceSalaryService::createAdvance started', [
                    'company_id' => $dto->companyId,
                    'employee_id' => $dto->employeeId,
                    'salary_type' => $dto->salaryType,
                    'amount' => $dto->advanceAmount,
                    'month_year' => $dto->monthYear
                ]);

                // Check permission based on salary type
                if ($dto->salaryType === 'loan') {
                    if (!$this->permissionService->checkPermissionWithFallback($user, 'hrloan', 'loan2')) {
                        throw new \Exception('ليس لديك صلاحية لإنشاء طلب قرض');
                    }
                } elseif ($dto->salaryType === 'advance') {
                    if (!$this->permissionService->checkPermissionWithFallback($user, 'hradvance_salary', 'advance_salary2')) {
                        throw new \Exception('ليس لديك صلاحية لإنشاء طلب سلفة');
                    }
                }

                $advance = $this->advanceSalaryRepository->createAdvance($dto);
                
                Log::info('AdvanceSalaryService::createAdvance completed successfully', [
                    'advance_id' => $advance->advance_salary_id,
                    'salary_type' => $advance->salary_type,
                    'amount' => $advance->advance_amount
                ]);

                return AdvanceSalaryResponseDTO::fromModel($advance);
            } catch (\Exception $e) {
                Log::error('AdvanceSalaryService::createAdvance failed', [
                    'error' => $e->getMessage(),
                    'company_id' => $dto->companyId,
                    'employee_id' => $dto->employeeId,
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Get advance salary/loan by ID with permission check
     * 
     * @param int $id Advance ID
     * @param User $user The user requesting the record
     * @param int|null $companyId Company ID (for company users/admins)
     * @param int|null $userId User ID (for regular employees)
     * @return AdvanceSalaryResponseDTO|null
     * @throws \Exception
     */
    public function getAdvanceById(int $id, User $user, ?int $companyId = null, ?int $userId = null): ?AdvanceSalaryResponseDTO
    {
        Log::info('AdvanceSalaryService::getAdvanceById - Starting', [
            'advance_id' => $id,
            'company_id' => $companyId,
            'user_id' => $userId,
            'requesting_user_id' => $user->user_id
        ]);

        if (is_null($companyId) && is_null($userId)) {
            Log::error('AdvanceSalaryService::getAdvanceById - Invalid arguments', [
                'advance_id' => $id
            ]);
            throw new \InvalidArgumentException('يجب توفير معرف الشركة أو معرف المستخدم');
        }

        $advance = null;

        // Find advance by company ID (for company users/admins)
        if ($companyId !== null) {
            $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $companyId);
            
            if ($advance) {
                Log::info('AdvanceSalaryService::getAdvanceById - Found by company', [
                    'advance_id' => $id,
                    'company_id' => $companyId
                ]);
            }
        }
        
        // Find advance by user ID (for regular employees)
        if (!$advance && $userId !== null) {
            $advance = $this->advanceSalaryRepository->findAdvanceForEmployee($id, $userId);
            
            if ($advance) {
                Log::info('AdvanceSalaryService::getAdvanceById - Found by employee', [
                    'advance_id' => $id,
                    'user_id' => $userId
                ]);
            }
        }

        if (!$advance) {
            Log::warning('AdvanceSalaryService::getAdvanceById - Not found', [
                'advance_id' => $id,
                'company_id' => $companyId,
                'user_id' => $userId
            ]);
            return null;
        }

        // Check permission for the specific record type
        $isOwner = $advance->employee_id === $user->user_id;
        $hasViewPermission = false;
        $isCompanyOwner = $this->permissionService->isCompanyOwner($user);

        if ($advance->salary_type === 'loan') {
            $hasViewPermission = $this->permissionService->checkPermissionWithFallback($user, 'hrloan', 'loan1');
        } elseif ($advance->salary_type === 'advance') {
            $hasViewPermission = $this->permissionService->checkPermissionWithFallback($user, 'hradvance_salary', 'advance_salary1');
        }

        // Allow if user is owner, has view permission for this type, or is company owner
        if (!$isOwner && !$hasViewPermission && !$isCompanyOwner) {
            Log::warning('AdvanceSalaryService::getAdvanceById - Permission denied', [
                'advance_id' => $id,
                'user_id' => $user->user_id,
                'advance_employee_id' => $advance->employee_id,
                'salary_type' => $advance->salary_type,
                'has_view_permission' => $hasViewPermission
            ]);
            throw new \Exception('ليس لديك صلاحية لعرض هذا الطلب');
        }

        return AdvanceSalaryResponseDTO::fromModel($advance);
    }

    /**
     * Update advance salary/loan request with permission check
     */
    public function updateAdvance(int $id, UpdateAdvanceSalaryDTO $dto, User $user): ?AdvanceSalaryResponseDTO
    {
        \DB::beginTransaction();
        try {
            \Log::info('AdvanceSalaryService::updateAdvance started', [
                'advance_id' => $id,
                'user_id' => $user->user_id,
                'updates' => array_keys(array_filter($dto->toArray()))
            ]);
            
            // Get effective company ID first
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            
            // Find advance without loading relationships first
            $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $effectiveCompanyId);
            
            if (!$advance) {
                \Log::warning('Advance not found', ['advance_id' => $id, 'company_id' => $effectiveCompanyId]);
                \DB::rollBack();
                return null;
            }

            // Check permissions based on salary type
            $isOwner = $advance->employee_id === $user->user_id;
            $hasParentPermission = false;
            $hasEditPermission = false;
            
            if ($advance->salary_type === 'loan') {
                $hasParentPermission = $this->permissionService->checkPermission($user, 'hrloan');
                $hasEditPermission = $this->permissionService->checkPermissionWithFallback($user, 'hrloan', 'loan3');
            } elseif ($advance->salary_type === 'advance') {
                $hasParentPermission = $this->permissionService->checkPermission($user, 'hradvance_salary');
                $hasEditPermission = $this->permissionService->checkPermissionWithFallback($user, 'hradvance_salary', 'advance_salary3');
            }
            
            // Managers/HR with parent permission can edit any request
            // Regular employees with edit permission can only edit their own requests
            if ($hasParentPermission) {
                // Manager/HR can edit any request - permission granted
            } elseif ($isOwner && $hasEditPermission) {
                // Regular employee can edit their own request if they have edit permission
            } else {
                \Log::warning('Permission denied - not owner or no edit permission', [
                    'advance_employee_id' => $advance->employee_id,
                    'current_user_id' => $user->user_id,
                    'has_parent_permission' => $hasParentPermission,
                    'has_edit_permission' => $hasEditPermission,
                    'is_owner' => $isOwner
                ]);
                \DB::rollBack();
                throw new \Exception('ليس لديك صلاحية لتعديل هذا الطلب');
            }

            // Check if advance can be updated (only pending)
            if ($advance->status !== 0) {
                \Log::warning('Cannot update - not pending', ['status' => $advance->status]);
                \DB::rollBack();
                throw new \Exception('لا يمكن تعديل الطلب بعد المراجعة');
            }

            // Update advance
            $updatedAdvance = $this->advanceSalaryRepository->updateAdvance($advance, $dto);
            
            \DB::commit();
            
            \Log::info('Advance updated successfully', [
                'advance_id' => $updatedAdvance->advance_salary_id,
                'updates' => array_keys(array_filter($dto->toArray()))
            ]);
            
            return AdvanceSalaryResponseDTO::fromModel($updatedAdvance);
            
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Error in AdvanceSalaryService::updateAdvance', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Cancel advance salary/loan request (mark as rejected/cancelled)
     */
    public function cancelAdvance(int $id, User $user): bool
    {
        return \DB::transaction(function () use ($id, $user) {
            try {
                Log::info('AdvanceSalaryService::cancelAdvance started', [
                    'advance_id' => $id,
                    'user_id' => $user->user_id,
                    'user_type' => $user->user_type
                ]);

                // Get effective company ID
                $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
                
                // Find advance in same company
                $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $effectiveCompanyId);
                
                if (!$advance) {
                    Log::warning('AdvanceSalaryService::cancelAdvance - Advance not found', [
                        'advance_id' => $id,
                        'company_id' => $effectiveCompanyId
                    ]);
                    return false;
                }

                // Check permissions based on salary type
                $isOwner = $advance->employee_id === $user->user_id;
                $hasParentPermission = false;
                $hasCancelPermission = false;
                
                if ($advance->salary_type === 'loan') {
                    $hasParentPermission = $this->permissionService->checkPermission($user, 'hrloan');
                    $hasCancelPermission = $this->permissionService->checkPermissionWithFallback($user, 'hrloan', 'loan4');
                } elseif ($advance->salary_type === 'advance') {
                    $hasParentPermission = $this->permissionService->checkPermission($user, 'hradvance_salary');
                    $hasCancelPermission = $this->permissionService->checkPermissionWithFallback($user, 'hradvance_salary', 'advance_salary4');
                }
                
                // Managers/HR with parent permission can cancel any request
                // Regular employees with cancel permission can only cancel their own pending requests
                if ($hasParentPermission) {
                    // Manager/HR can cancel any request
                } elseif ($isOwner && $hasCancelPermission) {
                    // Regular employee can cancel their own pending requests only
                    if ($advance->status !== 0) {
                        Log::warning('AdvanceSalaryService::cancelAdvance - Cannot cancel non-pending request', [
                            'advance_id' => $id,
                            'status' => $advance->status
                        ]);
                        throw new \Exception('لا يمكن إلغاء الطلب بعد المراجعة');
                    }
                } else {
                    Log::warning('AdvanceSalaryService::cancelAdvance - Permission denied', [
                        'advance_id' => $id,
                        'user_id' => $user->user_id,
                        'advance_employee_id' => $advance->employee_id,
                        'has_parent_permission' => $hasParentPermission,
                        'has_cancel_permission' => $hasCancelPermission
                    ]);
                    throw new \Exception('ليس لديك صلاحية لإلغاء هذا الطلب');
                }

                // Mark as rejected/cancelled (keeps record in database for audit trail)
                $cancelReason = $hasParentPermission ? 'تم إلغاء الطلب من قبل الإدارة' : 'تم إلغاء الطلب من قبل الموظف';
                $this->advanceSalaryRepository->rejectAdvance($advance, $user->user_id, $cancelReason);
                
                Log::info('AdvanceSalaryService::cancelAdvance completed successfully', [
                    'advance_id' => $id,
                    'cancelled_by' => $user->user_id,
                    'has_parent_permission' => $hasParentPermission,
                    'cancel_reason' => $cancelReason
                ]);

                return true;
            } catch (\Exception $e) {
                Log::error('AdvanceSalaryService::cancelAdvance failed', [
                    'advance_id' => $id,
                    'user_id' => $user->user_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Approve advance salary/loan request
     */
    public function approveAdvance(int $id, int $companyId, User $user, ?string $remarks = null): ?AdvanceSalaryResponseDTO
    {
        return \DB::transaction(function () use ($id, $companyId, $user, $remarks) {
            try {
                Log::info('AdvanceSalaryService::approveAdvance started', [
                    'advance_id' => $id,
                    'company_id' => $companyId,
                    'approved_by' => $user->user_id,
                    'has_remarks' => !empty($remarks)
                ]);

                $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $companyId);
                
                if (!$advance) {
                    Log::warning('AdvanceSalaryService::approveAdvance - Advance not found', [
                        'advance_id' => $id,
                        'company_id' => $companyId
                    ]);
                    return null;
                }

                // Check parent permission based on salary type
                $hasPermission = false;
                if ($advance->salary_type === 'loan') {
                    $hasPermission = $this->permissionService->checkPermission($user, 'hrloan');
                } elseif ($advance->salary_type === 'advance') {
                    $hasPermission = $this->permissionService->checkPermission($user, 'hradvance_salary');
                }
                
                if (!$hasPermission) {
                    Log::warning('AdvanceSalaryService::approveAdvance - Permission denied', [
                        'advance_id' => $id,
                        'user_id' => $user->user_id,
                        'salary_type' => $advance->salary_type
                    ]);
                    throw new \Exception('ليس لديك صلاحية للموافقة على هذا الطلب');
                }

                if ($advance->status !== 0) {
                    Log::warning('AdvanceSalaryService::approveAdvance - Cannot approve non-pending request', [
                        'advance_id' => $id,
                        'current_status' => $advance->status
                    ]);
                    throw new \Exception('تم الموافقة على هذا الطلب مسبقاً أو تم رفضه');
                }

                $approvedAdvance = $this->advanceSalaryRepository->approveAdvance($advance, $user->user_id, $remarks);
                
                Log::info('AdvanceSalaryService::approveAdvance completed successfully', [
                    'advance_id' => $id,
                    'employee_id' => $advance->employee_id,
                    'amount' => $advance->advance_amount,
                    'salary_type' => $advance->salary_type,
                    'approved_by' => $user->user_id
                ]);

                return AdvanceSalaryResponseDTO::fromModel($approvedAdvance);
            } catch (\Exception $e) {
                Log::error('AdvanceSalaryService::approveAdvance failed', [
                    'advance_id' => $id,
                    'company_id' => $companyId,
                    'approved_by' => $user->user_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Reject advance salary/loan request
     */
    public function rejectAdvance(int $id, int $companyId, User $user, string $reason): ?AdvanceSalaryResponseDTO
    {
        return \DB::transaction(function () use ($id, $companyId, $user, $reason) {
            try {
                Log::info('AdvanceSalaryService::rejectAdvance started', [
                    'advance_id' => $id,
                    'company_id' => $companyId,
                    'rejected_by' => $user->user_id,
                    'reason_length' => strlen($reason)
                ]);

                $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $companyId);
                
                if (!$advance) {
                    Log::warning('AdvanceSalaryService::rejectAdvance - Advance not found', [
                        'advance_id' => $id,
                        'company_id' => $companyId
                    ]);
                    return null;
                }

                // Check parent permission based on salary type (similar to approve)
                $hasPermission = false;
                if ($advance->salary_type === 'loan') {
                    $hasPermission = $this->permissionService->checkPermission($user, 'hrloan');
                } elseif ($advance->salary_type === 'advance') {
                    $hasPermission = $this->permissionService->checkPermission($user, 'hradvance_salary');
                }
                
                if (!$hasPermission) {
                    Log::warning('AdvanceSalaryService::rejectAdvance - Permission denied', [
                        'advance_id' => $id,
                        'user_id' => $user->user_id,
                        'salary_type' => $advance->salary_type
                    ]);
                    throw new \Exception('ليس لديك صلاحية لرفض هذا الطلب');
                }

                if ($advance->status !== 0) {
                    Log::warning('AdvanceSalaryService::rejectAdvance - Cannot reject non-pending request', [
                        'advance_id' => $id,
                        'current_status' => $advance->status
                    ]);
                    throw new \Exception('لا يمكن رفض طلب تم الموافقة عليه مسبقاً');
                }

                $rejectedAdvance = $this->advanceSalaryRepository->rejectAdvance($advance, $user->user_id, $reason);
                
                Log::info('AdvanceSalaryService::rejectAdvance completed successfully', [
                    'advance_id' => $id,
                    'employee_id' => $advance->employee_id,
                    'amount' => $advance->advance_amount,
                    'salary_type' => $advance->salary_type,
                    'rejected_by' => $user->user_id,
                    'rejection_reason' => $reason
                ]);

                return AdvanceSalaryResponseDTO::fromModel($rejectedAdvance);
            } catch (\Exception $e) {
                Log::error('AdvanceSalaryService::rejectAdvance failed', [
                    'advance_id' => $id,
                    'company_id' => $companyId,
                    'rejected_by' => $user->user_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Get advance salary/loan statistics
     */
    public function getAdvanceStatistics(int $companyId): array
    {
        return $this->advanceSalaryRepository->getAdvanceStatistics($companyId);
    }

    /**
     * Update total paid amount
     */
    public function updateTotalPaid(int $id, int $companyId, float $amount): ?AdvanceSalaryResponseDTO
    {
        $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $companyId);
        
        if (!$advance) {
            return null;
        }

        if ($advance->status !== 1) {
            throw new \Exception('يمكن تحديث المبلغ المدفوع للطلبات الموافق عليها فقط');
        }

        $updatedAdvance = $this->advanceSalaryRepository->updateTotalPaid($advance, $amount);
        return AdvanceSalaryResponseDTO::fromModel($updatedAdvance);
    }

    /**
     * Mark as deducted from salary
     */
    public function markAsDeducted(int $id, int $companyId): ?AdvanceSalaryResponseDTO
    {
        $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $companyId);
        
        if (!$advance) {
            return null;
        }

        if ($advance->status !== 1) {
            throw new \Exception('يمكن تحديد الخصم من الراتب للطلبات الموافق عليها فقط');
        }

        $updatedAdvance = $this->advanceSalaryRepository->markAsDeducted($advance);
        return AdvanceSalaryResponseDTO::fromModel($updatedAdvance);
    }
}

