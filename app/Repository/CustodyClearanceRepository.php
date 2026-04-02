<?php

namespace App\Repository;

use App\DTOs\CustodyClearance\CustodyFilterDTO;
use App\DTOs\CustodyClearance\CustodyClearanceFilterDTO;
use App\DTOs\CustodyClearance\CreateCustodyClearanceDTO;
use App\Models\Asset;
use App\Models\CustodyClearance;
use App\Models\CustodyClearanceItem;
use App\Models\User;
use App\Repository\Interface\CustodyClearanceRepositoryInterface;
use Illuminate\Support\Facades\Log;

class CustodyClearanceRepository implements CustodyClearanceRepositoryInterface
{
    /**
     * الحصول على العهد/الأصول للموظف
     */
    public function getCustodiesForEmployee(CustodyFilterDTO $filters): mixed
    {
        $query = Asset::with(['employee', 'brand', 'category']);

        // Filter by company
        if ($filters->companyId !== null) {
            $query->where('company_id', $filters->companyId);
        }

        // Filter by specific employee
        if ($filters->employeeId !== null) {
            $query->where('employee_id', $filters->employeeId);
        }

        // Filter by employee IDs list (for managers)
        if ($filters->employeeIds !== null && !empty($filters->employeeIds)) {
            $query->whereIn('employee_id', $filters->employeeIds);
        }

        // Filter by status
        if ($filters->status !== null) {
            if ($filters->status === 'working') {
                $query->where('is_working', 1);
            } else {
                $query->where('is_working', 0);
            }
        }

        // Search
        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('serial_number', 'like', $searchTerm)
                    ->orWhere('company_asset_code', 'like', $searchTerm);
            });
        }

        // Default to working assets only
        if ($filters->status === null) {
            $query->where('is_working', 1);
        }

        // Pagination
        if ($filters->paginate) {
            return $query->orderBy('name')
                ->paginate($filters->perPage, ['*'], 'page', $filters->page);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * الحصول على قائمة طلبات الإخلاء مع التصفية والترقيم
     */
    public function getPaginatedClearances(CustodyClearanceFilterDTO $filters, User $user): mixed
    {
        $query = CustodyClearance::with(['employee', 'approver', 'creator', 'items.asset', 'approvals.staff']);

        // Filter by company
        if ($filters->companyId !== null) {
            $query->where('company_id', $filters->companyId);
        }

        // Filter by specific employee
        if ($filters->employeeId !== null) {
            $query->where('employee_id', $filters->employeeId);
        }

        // Filter by employee IDs list (for managers)
        if ($filters->employeeIds !== null && !empty($filters->employeeIds)) {
            $query->whereIn('employee_id', $filters->employeeIds);
        }

        // Filter by status
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        // Filter by clearance type
        if ($filters->clearanceType !== null) {
            $query->where('clearance_type', $filters->clearanceType);
        }

        // Date filters
        if ($filters->fromDate !== null && $filters->toDate !== null) {
            $query->whereBetween('clearance_date', [$filters->fromDate, $filters->toDate]);
        } elseif ($filters->fromDate !== null) {
            $query->where('clearance_date', '>=', $filters->fromDate);
        } elseif ($filters->toDate !== null) {
            $query->where('clearance_date', '<=', $filters->toDate);
        }

        // Search
        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('notes', 'like', $searchTerm)
                    ->orWhereHas('employee', function ($subQuery) use ($searchTerm) {
                        $subQuery->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    });
            });
        }

        // Order by created_at desc
        $query->orderBy('created_at', 'desc');

        // Pagination
        if ($filters->paginate) {
            return $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
        }

        return $query->get();
    }

    /**
     * الحصول على طلب إخلاء بواسطة المعرف
     */
    public function findClearanceById(int $id, int $companyId): ?CustodyClearance
    {
        return CustodyClearance::with(['employee', 'approver', 'creator', 'items.asset', 'approvals.staff'])
            ->where('clearance_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * إنشاء طلب إخلاء جديد
     */
    public function createClearance(CreateCustodyClearanceDTO $dto): CustodyClearance
    {
        $clearance = CustodyClearance::create($dto->toArray());

        Log::info('CustodyClearance created', [
            'clearance_id' => $clearance->clearance_id,
            'employee_id' => $clearance->employee_id,
        ]);

        $clearance->load(['employee', 'approver', 'creator', 'items.asset', 'approvals.staff']);

        return $clearance;
    }

    /**
     * إضافة عناصر للإخلاء
     */
    public function addClearanceItems(int $clearanceId, array $assetIds): void
    {
        foreach ($assetIds as $assetId) {
            CustodyClearanceItem::create([
                'clearance_id' => $clearanceId,
                'asset_id' => $assetId,
                'return_date' => now(), // Assume returned today
                'created_at' => now(),
            ]);
        }

        Log::info('CustodyClearanceItems added', [
            'clearance_id' => $clearanceId,
            'asset_count' => count($assetIds),
        ]);
    }

    /**
     * الموافقة على طلب إخلاء
     */
    public function approveClearance(CustodyClearance $clearance, int $approvedBy, ?string $remarks = null): CustodyClearance
    {
        $clearance->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_date' => now(),
        ]);

        // Note: Approval recording is handled by ApprovalService to avoid duplicates

        $clearance->refresh();
        $clearance->load(['employee', 'approver', 'creator', 'items.asset', 'approvals.staff']);

        Log::info('CustodyClearance approved', [
            'clearance_id' => $clearance->clearance_id,
            'approved_by' => $approvedBy,
        ]);

        return $clearance;
    }

    /**
     * رفض طلب إخلاء
     */
    public function rejectClearance(CustodyClearance $clearance, int $rejectedBy, ?string $remarks = null): CustodyClearance
    {
        $clearance->update([
            'status' => 'rejected',
        ]);

        // Note: Rejection recording is handled by ApprovalService to avoid duplicates

        $clearance->refresh();
        $clearance->load(['employee', 'approver', 'creator', 'items.asset', 'approvals.staff']);

        Log::info('CustodyClearance rejected', [
            'clearance_id' => $clearance->clearance_id,
            'rejected_by' => $rejectedBy,
        ]);

        return $clearance;
    }

    /**
     * إلغاء طلب إخلاء
     */
    public function cancelClearance(CustodyClearance $clearance): bool
    {
        // Delete items first
        CustodyClearanceItem::where('clearance_id', $clearance->clearance_id)->delete();

        // Delete clearance
        $deleted = $clearance->delete();

        Log::info('CustodyClearance cancelled', [
            'clearance_id' => $clearance->clearance_id,
        ]);

        return $deleted;
    }

    /**
     * الحصول على جميع العهد للموظف
     */
    public function getAllCustodiesForEmployee(int $employeeId): array
    {
        return Asset::with(['brand', 'category'])
            ->where('employee_id', $employeeId)
            ->where('is_working', 1)
            ->get()
            ->toArray();
    }
}
