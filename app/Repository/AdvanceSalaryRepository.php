<?php

namespace App\Repository;

use App\Repository\Interface\AdvanceSalaryRepositoryInterface;
use App\DTOs\AdvanceSalary\AdvanceSalaryFilterDTO;
use App\DTOs\AdvanceSalary\CreateAdvanceSalaryDTO;
use App\DTOs\AdvanceSalary\UpdateAdvanceSalaryDTO;
use App\Models\AdvanceSalary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class AdvanceSalaryRepository implements AdvanceSalaryRepositoryInterface
{
    /**
     * Get paginated advance salary/loan requests with filters
     */
    public function getPaginatedAdvances(AdvanceSalaryFilterDTO $filters): LengthAwarePaginator
    {
        $query = AdvanceSalary::with(['employee', 'approvals.staff']);


        // Apply filters
        if ($filters->companyId !== null) {
            $query->where('company_id', $filters->companyId);
        }

        if ($filters->employeeId !== null) {
            $query->where('employee_id', $filters->employeeId);
        }

        if ($filters->employeeIds !== null && !empty($filters->employeeIds)) {
            $query->whereIn('employee_id', $filters->employeeIds);
        }

        if ($filters->salaryType !== null) {
            $query->where('salary_type', $filters->salaryType);
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->monthYear !== null) {
            $query->where('month_year', $filters->monthYear);
        }

        if ($filters->fromDate !== null) {
            $query->where('created_at', '>=', $filters->fromDate);
        }

        if ($filters->toDate !== null) {
            $query->where('created_at', '<=', $filters->toDate);
        }

        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                // البحث في بيانات الموظف
                $q->whereHas('employee', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('first_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm);
                });

                // البحث في الموظفين الذين قاموا بالموافقة (nested relationship)
                $q->orWhereHas('approvals.staff', function ($approvalQuery) use ($searchTerm) {
                    $approvalQuery->where('first_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm);
                });
            });
        }

        // Apply sorting
        $query->orderBy($filters->sortBy, $filters->sortDirection);

        return $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    /**
     * Create a new advance salary/loan request
     */
    public function createAdvance(CreateAdvanceSalaryDTO $dto): AdvanceSalary
    {
        Log::debug('AdvanceSalaryRepository::createAdvance - Creating record', [
            'employee_id' => $dto->employeeId,
            'salary_type' => $dto->salaryType,
            'amount' => $dto->advanceAmount
        ]);

        $advance = AdvanceSalary::create($dto->toArray());
        $advance->load(['employee']);

        Log::debug('AdvanceSalaryRepository::createAdvance - Record created', [
            'advance_id' => $advance->advance_salary_id
        ]);

        return $advance;
    }

    /**
     * Find advance salary/loan by ID
     */
    public function findAdvance(int $id): ?AdvanceSalary
    {
        return AdvanceSalary::with(['employee'])
            ->find($id);
    }

    public function findAdvanceInCompany(int $id, int $companyId): ?AdvanceSalary
    {
        return AdvanceSalary::with(['employee'])
            ->where('advance_salary_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Find advance salary/loan by Employee ID for specific company and status = 1 (Approved) and NOT completed
     */
    public function findApprovedAdvanceInCompany(int $employeeId, int $companyId): ?AdvanceSalary
    {
        return AdvanceSalary::with(['employee'])
            ->where('employee_id', $employeeId)
            ->where('company_id', $companyId)
            ->where('status', '=', 1) // Approved
            ->whereColumn('total_paid', '<', 'advance_amount') // total_paid < advance_amount (Not fully paid)
            ->first();
    }

    public function findPendingAdvanceInCompany(int $employeeId, int $companyId): ?AdvanceSalary
    {
        return AdvanceSalary::with(['employee'])
            ->where('employee_id', $employeeId)
            ->where('company_id', $companyId)
            ->where('status', '=', 0) // Pending
            ->first();
    }



    /**
     * Find advance salary/loan by ID for specific employee
     */
    public function findAdvanceForEmployee(int $id, int $employeeId): ?AdvanceSalary
    {
        return AdvanceSalary::with(['employee'])
            ->where('advance_salary_id', $id)
            ->where('employee_id', $employeeId)
            ->first();
    }

    /**
     * Update advance salary/loan request
     */
    public function updateAdvance(AdvanceSalary $advance, UpdateAdvanceSalaryDTO $dto): AdvanceSalary
    {
        Log::debug('AdvanceSalaryRepository::updateAdvance - Starting update', [
            'advance_id' => $advance->advance_salary_id,
            'updates' => $dto->toArray()
        ]);

        try {
            if ($dto->hasUpdates()) {
                $updates = $dto->toArray();
                Log::debug('AdvanceSalaryRepository::updateAdvance - Applying updates', ['updates' => $updates]);

                // Update using Eloquent's update method
                $advance->update($updates);

                // Refresh to get latest data
                $advance->refresh();

                // Load relationships
                $advance->load(['employee']);
            }

            Log::debug('AdvanceSalaryRepository::updateAdvance - Update completed', [
                'advance_id' => $advance->advance_salary_id
            ]);

            return $advance;
        } catch (\Exception $e) {
            Log::error('AdvanceSalaryRepository::updateAdvance - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Approve advance salary/loan request
     */
    public function approveAdvance(AdvanceSalary $advance, int $approvedBy, ?string $remarks = null): AdvanceSalary
    {
        Log::debug('AdvanceSalaryRepository::approveAdvance - Updating status', [
            'advance_id' => $advance->advance_salary_id,
            'approved_by' => $approvedBy
        ]);

        $advance->update([
            'status' => 1, // Approved
        ]);

        // Create approval record
        \App\Models\StaffApproval::create([
            'company_id' => $advance->company_id,
            'staff_id' => $approvedBy,
            'module_option' => 'advance_salary_settings',
            'module_key_id' => $advance->advance_salary_id,
            'status' => 1, // Approved
            'approval_level' => '1',
            'updated_at' => now(),
        ]);

        $advance->refresh();
        $advance->load(['employee', 'approvals.staff']); // Load approvals too

        Log::debug('AdvanceSalaryRepository::approveAdvance - Approved', [
            'advance_id' => $advance->advance_salary_id
        ]);

        return $advance;
    }

    /**
     * Reject advance salary/loan request
     */
    public function rejectAdvance(AdvanceSalary $advance, int $rejectedBy, string $reason): AdvanceSalary
    {
        Log::debug('AdvanceSalaryRepository::rejectAdvance - Updating status', [
            'advance_id' => $advance->advance_salary_id,
            'rejected_by' => $rejectedBy,
            'reason' => $reason
        ]);

        $advance->update([
            'status' => 2, // Rejected
            'reason' => $advance->reason . "\n\nسبب الرفض: " . $reason,
        ]);

        // Create rejection record
        \App\Models\StaffApproval::create([
            'company_id' => $advance->company_id,
            'staff_id' => $rejectedBy,
            'module_option' => 'advance_salary_settings',
            'module_key_id' => $advance->advance_salary_id,
            'status' => 2, // Rejected
            'approval_level' => '1',
            'updated_at' => now(),
        ]);

        $advance->refresh();
        $advance->load(['employee', 'approvals.staff']);

        Log::debug('AdvanceSalaryRepository::rejectAdvance - Rejected', [
            'advance_id' => $advance->advance_salary_id
        ]);

        return $advance;
    }


    /**
     * Get advance salary/loan statistics for company
     */
    public function getAdvanceStatistics(int $companyId): array
    {
        $totalAdvances = AdvanceSalary::where('company_id', $companyId)->count();
        $totalLoans = AdvanceSalary::where('company_id', $companyId)
            ->where('salary_type', 'loan')
            ->count();
        $totalAdvanceSalaries = AdvanceSalary::where('company_id', $companyId)
            ->where('salary_type', 'advance')
            ->count();

        $pendingCount = AdvanceSalary::where('company_id', $companyId)
            ->where('status', 0)
            ->count();
        $approvedCount = AdvanceSalary::where('company_id', $companyId)
            ->where('status', 1)
            ->count();
        $rejectedCount = AdvanceSalary::where('company_id', $companyId)
            ->where('status', 2)
            ->count();

        $totalAmount = AdvanceSalary::where('company_id', $companyId)
            ->where('status', 1)
            ->sum('advance_amount');
        $totalPaid = AdvanceSalary::where('company_id', $companyId)
            ->where('status', 1)
            ->sum('total_paid');
        $totalRemaining = $totalAmount - $totalPaid;

        $loanAmount = AdvanceSalary::where('company_id', $companyId)
            ->where('salary_type', 'loan')
            ->where('status', 1)
            ->sum('advance_amount');
        $advanceAmount = AdvanceSalary::where('company_id', $companyId)
            ->where('salary_type', 'advance')
            ->where('status', 1)
            ->sum('advance_amount');

        return [
            'total_requests' => $totalAdvances,
            'total_loans' => $totalLoans,
            'total_advances' => $totalAdvanceSalaries,
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'rejected_count' => $rejectedCount,
            'total_amount' => (float) $totalAmount,
            'total_paid' => (float) $totalPaid,
            'total_remaining' => (float) $totalRemaining,
            'loan_amount' => (float) $loanAmount,
            'advance_amount' => (float) $advanceAmount,
        ];
    }

    /**
     * Update total paid amount
     */
    public function updateTotalPaid(AdvanceSalary $advance, float $amount): AdvanceSalary
    {
        $newTotalPaid = $advance->total_paid + $amount;

        // Ensure total paid doesn't exceed advance amount
        if ($newTotalPaid > $advance->advance_amount) {
            $newTotalPaid = $advance->advance_amount;
        }

        $advance->update([
            'total_paid' => $newTotalPaid,
        ]);

        $advance->refresh();
        $advance->load(['employee']);

        return $advance;
    }

    /**
     * Mark as deducted from salary
     */
    public function markAsDeducted(AdvanceSalary $advance): AdvanceSalary
    {
        $advance->update([
            'is_deducted_from_salary' => 1,
        ]);

        $advance->refresh();
        $advance->load(['employee']);

        return $advance;
    }
}
