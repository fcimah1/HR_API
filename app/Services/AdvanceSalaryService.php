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
        
        // If company owner, can see all requests in their company
        if ($this->permissionService->isCompanyOwner($user)) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
        } 
        // If employee has permission to view all requests
        elseif ($this->permissionService->checkPermission($user, 'advance.view.all')) {
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
        
        $advanceDTOs = collect($advances->items())->map(function ($advance) {
            return AdvanceSalaryResponseDTO::fromModel($advance);
        });

        return [
            'data' => $advanceDTOs->map(fn($dto) => $dto->toArray())->toArray(),
            'pagination' => [
                'current_page' => $advances->currentPage(),
                'last_page' => $advances->lastPage(),
                'per_page' => $advances->perPage(),
                'total' => $advances->total(),
                'from' => $advances->firstItem(),
                'to' => $advances->lastItem(),
                'has_more_pages' => $advances->hasMorePages(),
            ]
        ];
    }

    /**
     * Create a new advance salary/loan request with permission check
     */
    public function createAdvance(CreateAdvanceSalaryDTO $dto): AdvanceSalaryResponseDTO
    {
        return \DB::transaction(function () use ($dto) {
            try {
                Log::info('AdvanceSalaryService::createAdvance started', [
                    'company_id' => $dto->companyId,
                    'employee_id' => $dto->employeeId,
                    'salary_type' => $dto->salaryType,
                    'amount' => $dto->advanceAmount,
                    'month_year' => $dto->monthYear
                ]);

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
     * @param int|null $companyId Company ID (for company users/admins)
     * @param int|null $userId User ID (for regular employees)
     * @return AdvanceSalaryResponseDTO|null
     * @throws \Exception
     */
    public function getAdvanceById(int $id, ?int $companyId = null, ?int $userId = null): ?AdvanceSalaryResponseDTO
    {
        Log::info('AdvanceSalaryService::getAdvanceById - Starting', [
            'advance_id' => $id,
            'company_id' => $companyId,
            'user_id' => $userId
        ]);

        if (is_null($companyId) && is_null($userId)) {
            Log::error('AdvanceSalaryService::getAdvanceById - Invalid arguments', [
                'advance_id' => $id
            ]);
            throw new \InvalidArgumentException('يجب توفير معرف الشركة أو معرف المستخدم');
        }

        // Find advance by company ID (for company users/admins)
        if ($companyId !== null) {
            $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $companyId);
            
            if ($advance) {
                Log::info('AdvanceSalaryService::getAdvanceById - Found by company', [
                    'advance_id' => $id,
                    'company_id' => $companyId
                ]);
                return AdvanceSalaryResponseDTO::fromModel($advance);
            }
        }
        
        // Find advance by user ID (for regular employees)
        if ($userId !== null) {
            $advance = $this->advanceSalaryRepository->findAdvanceForEmployee($id, $userId);
            
            if ($advance) {
                Log::info('AdvanceSalaryService::getAdvanceById - Found by employee', [
                    'advance_id' => $id,
                    'user_id' => $userId
                ]);
                return AdvanceSalaryResponseDTO::fromModel($advance);
            }
        }

        Log::warning('AdvanceSalaryService::getAdvanceById - Not found', [
            'advance_id' => $id,
            'company_id' => $companyId,
            'user_id' => $userId
        ]);

        return null;
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

            // Check permissions
            if ($advance->employee_id !== $user->user_id) {
                \Log::warning('Permission denied - not owner', [
                    'advance_employee_id' => $advance->employee_id,
                    'current_user_id' => $user->user_id
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

                // Check permissions:
                // 1. Employee owner can cancel their own pending requests only
                // 2. Manager/Company can cancel any request (pending or approved)
                $isOwner = $advance->employee_id === $user->user_id;
                $isManager = in_array($user->user_type, ['company', 'admin', 'hr', 'manager']);
                
                if (!$isOwner && !$isManager) {
                    Log::warning('AdvanceSalaryService::cancelAdvance - Permission denied', [
                        'advance_id' => $id,
                        'user_id' => $user->user_id,
                        'advance_employee_id' => $advance->employee_id
                    ]);
                    throw new \Exception('ليس لديك صلاحية لإلغاء هذا الطلب');
                }
                
                // Regular employee can only cancel pending requests
                if ($isOwner && !$isManager && $advance->status !== 0) {
                    Log::warning('AdvanceSalaryService::cancelAdvance - Cannot cancel non-pending request', [
                        'advance_id' => $id,
                        'status' => $advance->status
                    ]);
                    throw new \Exception('لا يمكن إلغاء الطلب بعد المراجعة');
                }

                // Mark as rejected/cancelled (keeps record in database for audit trail)
                $cancelReason = $isManager ? 'تم إلغاء الطلب من قبل الإدارة' : 'تم إلغاء الطلب من قبل الموظف';
                $this->advanceSalaryRepository->rejectAdvance($advance, $user->user_id, $cancelReason);
                
                Log::info('AdvanceSalaryService::cancelAdvance completed successfully', [
                    'advance_id' => $id,
                    'cancelled_by' => $user->user_id,
                    'is_manager' => $isManager,
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
    public function approveAdvance(int $id, int $companyId, int $approvedBy, ?string $remarks = null): ?AdvanceSalaryResponseDTO
    {
        return \DB::transaction(function () use ($id, $companyId, $approvedBy, $remarks) {
            try {
                Log::info('AdvanceSalaryService::approveAdvance started', [
                    'advance_id' => $id,
                    'company_id' => $companyId,
                    'approved_by' => $approvedBy,
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

                if ($advance->status !== 0) {
                    Log::warning('AdvanceSalaryService::approveAdvance - Cannot approve non-pending request', [
                        'advance_id' => $id,
                        'current_status' => $advance->status
                    ]);
                    throw new \Exception('تم الموافقة على هذا الطلب مسبقاً أو تم رفضه');
                }

                $approvedAdvance = $this->advanceSalaryRepository->approveAdvance($advance, $approvedBy, $remarks);
                
                Log::info('AdvanceSalaryService::approveAdvance completed successfully', [
                    'advance_id' => $id,
                    'employee_id' => $advance->employee_id,
                    'amount' => $advance->advance_amount,
                    'salary_type' => $advance->salary_type,
                    'approved_by' => $approvedBy
                ]);

                return AdvanceSalaryResponseDTO::fromModel($approvedAdvance);
            } catch (\Exception $e) {
                Log::error('AdvanceSalaryService::approveAdvance failed', [
                    'advance_id' => $id,
                    'company_id' => $companyId,
                    'approved_by' => $approvedBy,
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
    public function rejectAdvance(int $id, int $companyId, int $rejectedBy, string $reason): ?AdvanceSalaryResponseDTO
    {
        return \DB::transaction(function () use ($id, $companyId, $rejectedBy, $reason) {
            try {
                Log::info('AdvanceSalaryService::rejectAdvance started', [
                    'advance_id' => $id,
                    'company_id' => $companyId,
                    'rejected_by' => $rejectedBy,
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

                if ($advance->status !== 0) {
                    Log::warning('AdvanceSalaryService::rejectAdvance - Cannot reject non-pending request', [
                        'advance_id' => $id,
                        'current_status' => $advance->status
                    ]);
                    throw new \Exception('لا يمكن رفض طلب تم الموافقة عليه مسبقاً');
                }

                $rejectedAdvance = $this->advanceSalaryRepository->rejectAdvance($advance, $rejectedBy, $reason);
                
                Log::info('AdvanceSalaryService::rejectAdvance completed successfully', [
                    'advance_id' => $id,
                    'employee_id' => $advance->employee_id,
                    'amount' => $advance->advance_amount,
                    'salary_type' => $advance->salary_type,
                    'rejected_by' => $rejectedBy,
                    'rejection_reason' => $reason
                ]);

                return AdvanceSalaryResponseDTO::fromModel($rejectedAdvance);
            } catch (\Exception $e) {
                Log::error('AdvanceSalaryService::rejectAdvance failed', [
                    'advance_id' => $id,
                    'company_id' => $companyId,
                    'rejected_by' => $rejectedBy,
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

