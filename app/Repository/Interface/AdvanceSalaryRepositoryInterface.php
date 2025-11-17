<?php

namespace App\Repository\Interface;

use App\DTOs\AdvanceSalary\AdvanceSalaryFilterDTO;
use App\DTOs\AdvanceSalary\CreateAdvanceSalaryDTO;
use App\DTOs\AdvanceSalary\UpdateAdvanceSalaryDTO;
use App\Models\AdvanceSalary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AdvanceSalaryRepositoryInterface
{
    /**
     * Get paginated advance salary/loan requests with filters
     */
    public function getPaginatedAdvances(AdvanceSalaryFilterDTO $filters): LengthAwarePaginator;

    /**
     * Create a new advance salary/loan request
     */
    public function createAdvance(CreateAdvanceSalaryDTO $dto): AdvanceSalary;

    /**
     * Find advance salary/loan by ID
     */
    public function findAdvance(int $id): ?AdvanceSalary;

    /**
     * Find advance salary/loan by ID for specific company
     */
    public function findAdvanceInCompany(int $id, int $companyId): ?AdvanceSalary;

    /**
     * Find advance salary/loan by ID for specific employee
     */
    public function findAdvanceForEmployee(int $id, int $employeeId): ?AdvanceSalary;

    /**
     * Update advance salary/loan request
     */
    public function updateAdvance(AdvanceSalary $advance, UpdateAdvanceSalaryDTO $dto): AdvanceSalary;

    /**
     * Approve advance salary/loan request
     */
    public function approveAdvance(AdvanceSalary $advance, int $approvedBy, ?string $remarks = null): AdvanceSalary;

    /**
     * Reject advance salary/loan request
     */
    public function rejectAdvance(AdvanceSalary $advance, int $rejectedBy, string $reason): AdvanceSalary;


    /**
     * Get advance salary/loan statistics for company
     */
    public function getAdvanceStatistics(int $companyId): array;

    /**
     * Update total paid amount
     */
    public function updateTotalPaid(AdvanceSalary $advance, float $amount): AdvanceSalary;

    /**
     * Mark as deducted from salary
     */
    public function markAsDeducted(AdvanceSalary $advance): AdvanceSalary;
}

