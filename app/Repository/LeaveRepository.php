<?php

namespace App\Repository;

use App\DTOs\Leave\UpdateLeaveAdjustmentDTO;
use App\Repository\Interface\LeaveRepositoryInterface;
use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Leave\CreateLeaveApplicationDTO;
use App\DTOs\Leave\UpdateLeaveApplicationDTO;
use App\DTOs\Leave\LeaveAdjustmentFilterDTO;
use App\DTOs\Leave\CreateLeaveAdjustmentDTO;
use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\Models\LeaveApplication;
use App\Models\LeaveAdjustment;
use App\Models\ErpConstant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class LeaveRepository implements LeaveRepositoryInterface
{
    /**
     * Get paginated leave applications with filters
     */
    public function getPaginatedApplications(LeaveApplicationFilterDTO $filters): LengthAwarePaginator
    {
    $companyId = $filters->companyId ;
        $query = LeaveApplication::where('company_id', $companyId)->with(['employee', 'dutyEmployee', 'leaveType']);

        // Apply filters
        if ($filters->companyName !== null) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('company_name', $filters->companyName);
            });
        }

        if ($filters->employeeId !== null) {
            $query->where('employee_id', $filters->employeeId);
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->leaveTypeId !== null) {
            $query->where('leave_type_id', $filters->leaveTypeId);
        }

        if ($filters->fromDate !== null) {
            $query->where('from_date', '>=', $filters->fromDate);
        }

        if ($filters->toDate !== null) {
            $query->where('to_date', '<=', $filters->toDate);
        }

        // Apply sorting
        $query->orderBy($filters->sortBy, $filters->sortDirection);

        return $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    /**
     * Create a new leave application
     */
    public function createApplication(CreateLeaveApplicationDTO $dto): LeaveApplication
    {
        $application = LeaveApplication::create($dto->toArray());
        $application->load(['employee', 'dutyEmployee', 'leaveType']);
        
        return $application;
    }

    /**
     * Find leave application by ID
     */
    public function findApplication(int $id): ?LeaveApplication
    {
        return LeaveApplication::with(['employee', 'dutyEmployee', 'leaveType'])
            ->find($id);
    }

    /**
     * Find leave application by ID for specific company
     */
    public function findApplicationInCompany(int $id, int $companyId): ?LeaveApplication
    {
        return LeaveApplication::with(['employee', 'dutyEmployee', 'leaveType'])
            ->where('leave_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Find leave application by ID for specific employee
     */
    public function findApplicationForEmployee(int $id, int $employeeId): ?LeaveApplication
    {
        return LeaveApplication::with(['employee', 'dutyEmployee', 'leaveType'])
            ->where('leave_id', $id)
            ->where('employee_id', $employeeId)
            ->first();
    }

    /**
     * Update leave application
     */
    public function update_Application(LeaveApplication $application, UpdateLeaveApplicationDTO $dto): object
    {
        try {
            if ($dto->hasUpdates()) {
                $updates = $dto->toArray();
                
                // Update using Eloquent's update method (simpler and more reliable)
                $application->update($updates);
                
                // Refresh to get latest data
                $application->refresh();
                
                // Load relationships
                $application->load(['employee', 'dutyEmployee', 'leaveType']);
            }

            Log::debug('LeaveRepository::updateApplication - Update completed', [
                'application_id' => $application->leave_id
            ]);

            return $application;

        } catch (\Exception $e) {
            error_log("LeaveRepository::updateApplication - Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Approve leave application
     */
    public function approveApplication(LeaveApplication $application, int $approvedBy, ?string $remarks = null): LeaveApplication
    {
        $application->update([
            'status' => true,
            'remarks' => $remarks,
        ]);

        $application->refresh();
        $application->load(['employee', 'dutyEmployee', 'leaveType']);

        return $application;
    }

    /**
     * Reject leave application
     */
    public function rejectApplication(LeaveApplication $application, int $rejectedBy, string $reason): LeaveApplication
    {
        $application->update([
            'status' => false,
            'remarks' => $reason,
        ]);

        $application->refresh();
        $application->load(['employee', 'dutyEmployee', 'leaveType']);

        return $application;
    }

    /**
     * Get paginated leave adjustments with filters
     */
    public function getPaginatedAdjustments(LeaveAdjustmentFilterDTO $filters): LengthAwarePaginator
    {
        $companyId = $filters->companyId ;
        $query = LeaveAdjustment::where('company_id', $companyId)->with(['employee', 'dutyEmployee', 'leaveType']);

        // Apply filters
        if ($filters->companyName !== null) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('company_name', $filters->companyName);
            });
        }

        if ($filters->employeeId !== null) {
            $query->where('employee_id', $filters->employeeId);
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->leaveTypeId !== null) {
            $query->where('leave_type_id', $filters->leaveTypeId);
        }

        // Apply sorting
        $query->orderBy($filters->sortBy, $filters->sortDirection);

        return $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    /**
     * Create a new leave adjustment
     */
    public function createAdjust(CreateLeaveAdjustmentDTO $dto): object
    {
        $adjustment = LeaveAdjustment::create($dto->toArray());
        $adjustment->load(['employee', 'dutyEmployee', 'leaveType']);
        
        return $adjustment;
    }

    /**
     * Find leave adjustment by ID
     */
    public function findAdjustment(int $id): ?LeaveAdjustment
    {
        return LeaveAdjustment::with(['employee', 'dutyEmployee', 'leaveType'])
            ->find($id);
    }

    /**
     * Find leave adjustment by ID for specific company
     */
    public function findAdjustmentInCompany(int $id, int $companyId): ?LeaveAdjustment
    {
        return LeaveAdjustment::with(['employee', 'dutyEmployee', 'leaveType'])
            ->where('adjustment_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Approve leave adjustment
     */
    public function approveAdjustment(LeaveAdjustment $adjustment, int $approvedBy): LeaveAdjustment
    {
        $adjustment->update([
            'status' => LeaveAdjustment::STATUS_APPROVED,
        ]);

        $adjustment->refresh();
        $adjustment->load(['employee', 'dutyEmployee', 'leaveType']);

        return $adjustment;
    }

    /**
     * Reject leave adjustment
     */
    public function rejectAdjustment(LeaveAdjustment $adjustment, int $rejectedBy, string $reason): LeaveAdjustment
    {
        $adjustment->update([
            'status' => LeaveAdjustment::STATUS_REJECTED,
        ]);

        $adjustment->refresh();
        $adjustment->load(['employee', 'dutyEmployee', 'leaveType']);

        return $adjustment;
    }

    /**
     * Get leave statistics for company
     */
    public function getLeaveStatistics(int $companyId): array
    {
        
        $applicationStats = [
            'total_applications' => LeaveApplication::where('company_id', $companyId)->count(),
            'effective_company_id' => $companyId,
            'pending_applications' => LeaveApplication::where('company_id', $companyId)->where('status', false)->count(),
            'approved_applications' => LeaveApplication::where('company_id', $companyId)->where('status', true)->count(),
        ];

        $adjustmentStats = [
            'effective_company_id' => $companyId,
            'total_adjustments' => LeaveAdjustment::where('company_id', $companyId)->count(),
            'pending_adjustments' => LeaveAdjustment::where('company_id', $companyId)->where('status', LeaveAdjustment::STATUS_PENDING)->count(),
            'approved_adjustments' => LeaveAdjustment::where('company_id', $companyId)->where('status', LeaveAdjustment::STATUS_APPROVED)->count(),
        ];

        return [
            'applications' => $applicationStats,
            'adjustments' => $adjustmentStats,
        ];
    }

    /**
     * Get active leave types for company
     */
    public function getActiveLeaveTypes(int $companyId): Collection
    {
        return ErpConstant::getActiveLeaveTypes($companyId);
    }

    /**
     * Create leave type
     */
    public function createLeaveType(CreateLeaveTypeDTO $dto): object
    {
        // Check if leave type already exists for this company
        $existingLeaveType = ErpConstant::where('company_id', $dto->companyId)
            ->where('type', ErpConstant::TYPE_LEAVE_TYPE)
            ->where('category_name', $dto->name)
            ->first();
        
        if ($existingLeaveType) {
            throw new \Exception('نوع الإجازة "' . $dto->name . '" موجود بالفعل لهذه الشركة');
        }
        
        return ErpConstant::create($dto->toArray());
    }

    /**
     * Find leave adjustment for specific employee
     */
    public function findAdjustmentForEmployee(int $id, int $employeeId): ?LeaveAdjustment
    {
        return LeaveAdjustment::with(['employee', 'dutyEmployee', 'leaveType'])
            ->where('adjustment_id', $id)
            ->where('employee_id', $employeeId)
            ->first();
    }

    /**
     * Update leave adjustment
     */
    public function updateAdjustment(LeaveAdjustment $adjustment, UpdateLeaveAdjustmentDTO $dto): LeaveAdjustment
    {
        if ($dto->hasUpdates()) {
            $adjustment->update($dto->toArray());
            $adjustment->refresh();
            $adjustment->load(['employee', 'dutyEmployee', 'leaveType']);
        }

        return $adjustment;
    }

    /**
     * Cancel leave adjustment (mark as rejected)
     */
    public function cancelAdjustment(LeaveAdjustment $adjustment, int $cancelledBy, string $reason): LeaveAdjustment
    {
        $adjustment->update([
            'status' => LeaveAdjustment::STATUS_REJECTED,
            'reason_adjustment' => $reason,
        ]);

        $adjustment->refresh();
        $adjustment->load(['employee', 'dutyEmployee', 'leaveType']);

        return $adjustment;
    }

    /**
    * Get total granted leave for an employee*/
    public function getTotalGrantedLeave(int $employeeId, int $leaveTypeId, int $companyId): float
    {
        // use model ErpConstant
        return (float) ErpConstant::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->sum('days_granted');
    }

    /**
     * Get total used leave for an employee
     */
    public function getTotalUsedLeave(int $employeeId, int $leaveTypeId, int $companyId): float
    {
        // use model LeaveApplication
        return (float) LeaveApplication::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('company_id', $companyId)
            ->whereIn('status', ['approved', 'pending'])
            ->sum('days_requested');
    }
    }
