<?php

namespace App\Repository;

use App\Repository\Interface\LeaveRepositoryInterface;
use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Leave\CreateLeaveApplicationDTO;
use App\DTOs\Leave\UpdateLeaveApplicationDTO;
use App\DTOs\Leave\LeaveAdjustmentFilterDTO;
use App\DTOs\Leave\CreateLeaveAdjustmentDTO;
use App\Models\LeaveApplication;
use App\Models\LeaveAdjustment;
use App\Models\ErpConstant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LeaveRepository implements LeaveRepositoryInterface
{
    /**
     * Get paginated leave applications with filters
     */
    public function getPaginatedApplications(LeaveApplicationFilterDTO $filters): LengthAwarePaginator
    {
        $query = LeaveApplication::with(['employee', 'dutyEmployee', 'leaveType']);

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
    public function updateApplication(LeaveApplication $application, UpdateLeaveApplicationDTO $dto): LeaveApplication
    {
        \Log::debug('LeaveRepository::updateApplication - Starting update', [
            'application_id' => $application->leave_id,
            'updates' => $dto->toArray()
        ]);

        try {
            // Temporarily disable model events
            $dispatcher = $application->getEventDispatcher();
            $application->unsetEventDispatcher();

            if ($dto->hasUpdates()) {
                $updates = $dto->toArray();
                \Log::debug('Applying updates', ['updates' => $updates]);
                
                // Update the model directly without events
                \DB::table('leave_applications')
                    ->where('leave_id', $application->leave_id)
                    ->update($updates);
                    
                // Refresh the model
                $application = $application->fresh();
                
                // Reload relationships if needed
                if ($application) {
                    $application->load(['employee', 'dutyEmployee', 'leaveType']);
                }
            }

            // Re-enable events
            $application->setEventDispatcher($dispatcher);

            \Log::debug('LeaveRepository::updateApplication - Update completed', [
                'application_id' => $application->leave_id
            ]);

            return $application;

        } catch (\Exception $e) {
            \Log::error('LeaveRepository::updateApplication - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
     * Delete leave application completely
     */
    public function deleteApplication(LeaveApplication $application): bool
    {
        return $application->delete();
    }

    /**
     * Get paginated leave adjustments with filters
     */
    public function getPaginatedAdjustments(LeaveAdjustmentFilterDTO $filters): LengthAwarePaginator
    {
        $query = LeaveAdjustment::with(['employee', 'dutyEmployee', 'leaveType']);

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
    public function createAdjustment(CreateLeaveAdjustmentDTO $dto): LeaveAdjustment
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
            'pending_applications' => LeaveApplication::where('company_id', $companyId)->where('status', false)->count(),
            'approved_applications' => LeaveApplication::where('company_id', $companyId)->where('status', true)->count(),
        ];

        $adjustmentStats = [
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
    public function createLeaveType(int $companyId, string $name, ?string $shortName, int $days): ErpConstant
    {
        return ErpConstant::createLeaveType($companyId, $name, $shortName, $days);
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
     * Delete leave adjustment completely
     */
    public function deleteAdjustment(LeaveAdjustment $adjustment): bool
    {
        return $adjustment->delete();
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
}
