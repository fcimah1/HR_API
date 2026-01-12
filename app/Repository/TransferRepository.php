<?php

namespace App\Repository;

use App\DTOs\Transfer\CreateTransferDTO;
use App\DTOs\Transfer\TransferFilterDTO;
use App\DTOs\Transfer\UpdateTransferDTO;
use App\Enums\NumericalStatusEnum;
use App\Models\AdvanceSalary;
use App\Models\Asset;
use App\Models\LeaveApplication;
use App\Models\Transfer;
use App\Models\User;
use App\Repository\Interface\TransferRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferRepository implements TransferRepositoryInterface
{
    /**
     * الحصول على قائمة النقل مع التصفية والترقيم الصفحي
     */
    public function getPaginatedTransfers(TransferFilterDTO $filters, User $user): array
    {
        $query = Transfer::with([
            'employee',
            'addedBy',
            'oldDepartment',
            'newDepartment',
            'oldDesignation',
            'newDesignation',
            'oldBranch',
            'newBranch',
            'oldCurrency',
            'newCurrency',
            'oldCompany',
            'newCompany',
            'approvals.staff'
        ]);

        // تطبيق فلتر الشركة
        if ($filters->companyId !== null) {
            $query->where('company_id', $filters->companyId);
        }

        // تطبيق فلتر الموظف المحدد
        if ($filters->employeeId !== null) {
            $query->where('employee_id', $filters->employeeId);
        }

        // تطبيق فلتر قائمة الموظفين (للمديرين)
        if ($filters->employeeIds !== null && !empty($filters->employeeIds)) {
            $query->whereIn('employee_id', $filters->employeeIds);
        }

        // تطبيق فلتر الحالة
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        // تطبيق فلتر القسم
        if ($filters->departmentId !== null) {
            $query->where(function ($q) use ($filters) {
                $q->where('old_department', $filters->departmentId)
                    ->orWhere('transfer_department', $filters->departmentId);
            });
        }

        // تطبيق فلتر نوع النقل
        if ($filters->transferType !== null) {
            $query->where('transfer_type', $filters->transferType);
        }

        // تطبيق البحث
        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('reason', 'like', $searchTerm)
                    ->orWhereHas('employee', function ($subQuery) use ($searchTerm) {
                        $subQuery->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    });
            });
        }

        // تطبيق فلتر التاريخ
        if ($filters->fromDate !== null && $filters->toDate !== null) {
            $query->whereBetween('transfer_date', [$filters->fromDate, $filters->toDate]);
        } elseif ($filters->fromDate !== null) {
            $query->where('transfer_date', '>=', $filters->fromDate);
        } elseif ($filters->toDate !== null) {
            $query->where('transfer_date', '<=', $filters->toDate);
        }

        // ترتيب حسب التاريخ تنازليًا
        $query->orderBy('created_at', 'desc');

        // استخدام paginate
        $paginator = $query->paginate($filters->perPage, ['*'], 'page', $filters->page);

        return [
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * الحصول على نقل بواسطة المعرف
     */
    public function findTransferById(int $id, int $companyId): ?Transfer
    {
        return Transfer::with(['employee', 'addedBy', 'oldDepartment', 'newDepartment', 'oldDesignation', 'newDesignation', 'approvals.staff'])
            ->where('transfer_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * الحصول على نقل للموظف
     */
    public function findTransferForEmployee(int $id, int $employeeId): ?Transfer
    {
        return Transfer::with(['employee', 'addedBy', 'oldDepartment', 'newDepartment', 'oldDesignation', 'newDesignation', 'approvals.staff'])
            ->where('transfer_id', $id)
            ->where('employee_id', $employeeId)
            ->first();
    }

    /**
     * إنشاء نقل جديد
     */
    public function createTransfer(CreateTransferDTO $dto): Transfer
    {
        $transfer = Transfer::create($dto->toArray());
        $transfer->load(['employee', 'addedBy', 'oldDepartment', 'newDepartment', 'oldDesignation', 'newDesignation', 'approvals.staff']);

        Log::info('Transfer created', [
            'transfer_id' => $transfer->transfer_id,
            'employee_id' => $transfer->employee_id,
        ]);

        return $transfer;
    }

    /**
     * تحديث نقل
     */
    public function updateTransfer(Transfer $transfer, UpdateTransferDTO $dto): Transfer
    {
        $updateData = $dto->toArray();

        if (!empty($updateData)) {
            $transfer->update($updateData);
        }

        $transfer->refresh();
        $transfer->load(['employee', 'addedBy', 'oldDepartment', 'newDepartment', 'oldDesignation', 'newDesignation', 'approvals.staff']);

        Log::info('Transfer updated', [
            'transfer_id' => $transfer->transfer_id,
        ]);

        return $transfer;
    }

    /**
     * حذف نقل
     */
    public function deleteTransfer(Transfer $transfer): bool
    {
        Log::info('Transfer deleted', [
            'transfer_id' => $transfer->transfer_id,
            'employee_id' => $transfer->employee_id,
        ]);

        return $transfer->update([
            'status' => NumericalStatusEnum::REJECTED->value,
        ]);
    }

    /**
     * الموافقة على نقل
     */
    public function approveTransfer(Transfer $transfer, int $approvedBy, ?string $remarks = null): Transfer
    {
        $transfer->update([
            'status' => NumericalStatusEnum::APPROVED->value,
        ]);

        // Note: Approval recording is handled by ApprovalService to avoid duplicates

        $transfer->refresh();
        $transfer->load(['employee', 'addedBy', 'oldDepartment', 'newDepartment', 'oldDesignation', 'newDesignation', 'approvals.staff']);

        Log::info('Transfer approved', [
            'transfer_id' => $transfer->transfer_id,
            'approved_by' => $approvedBy,
            'remarks' => $remarks,
        ]);

        return $transfer;
    }

    /**
     * رفض نقل
     */
    public function rejectTransfer(Transfer $transfer, int $rejectedBy, ?string $remarks = null): Transfer
    {
        $transfer->update([
            'status' => NumericalStatusEnum::REJECTED->value,
        ]);

        // Note: Rejection recording is handled by ApprovalService to avoid duplicates

        $transfer->refresh();
        $transfer->load(['employee', 'addedBy', 'oldDepartment', 'newDepartment', 'oldDesignation', 'newDesignation', 'approvals.staff']);

        Log::info('Transfer rejected', [
            'transfer_id' => $transfer->transfer_id,
            'rejected_by' => $rejectedBy,
            'remarks' => $remarks,
        ]);

        return $transfer;
    }

    /**
     * الحصول على طلبات الإجازة النشطة للموظف
     */
    public function getActiveLeaves(int $employeeId): array
    {
        return LeaveApplication::where('employee_id', $employeeId)
            ->whereIn('status', [NumericalStatusEnum::PENDING->value, NumericalStatusEnum::APPROVED->value])
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereDate('from_date', '<=', now())
                        ->whereDate('to_date', '>=', now());
                })
                    ->orWhereDate('from_date', '>', now());
            })
            ->get(['leave_id', 'leave_type_id', 'from_date', 'to_date', 'status'])
            ->map(fn($item) => $item->only(['leave_id', 'leave_type_id', 'from_date', 'to_date', 'status']))
            ->toArray();
    }

    /**
     * الحصول على السلف النشطة للموظف
     */
    public function getActiveAdvances(int $employeeId): array
    {
        return AdvanceSalary::where('employee_id', $employeeId)
            ->where('status', NumericalStatusEnum::APPROVED->value)
            ->whereRaw('advance_amount > total_paid')
            ->get(['advance_salary_id', 'advance_amount', 'total_paid', 'monthly_installment', 'salary_type'])
            ->map(function ($item) {
                return [
                    'advance_id' => $item->advance_salary_id,
                    'amount' => $item->advance_amount,
                    'total_paid' => $item->total_paid,
                    'remaining' => $item->advance_amount - $item->total_paid,
                    'type' => $item->salary_type,
                ];
            })
            ->toArray();
    }

    /**
     * الحصول على العهد غير المرجعة للموظف (الأصول المخصصة له)
     */
    public function getUnreturnedCustody(int $employeeId): array
    {
        return Asset::forEmployee($employeeId)
            ->working()
            ->get(['assets_id', 'name', 'serial_number', 'company_asset_code', 'purchase_date'])
            ->map(fn($item) => $item->toArray())
            ->toArray();
    }

    /**
     * البحث عن طلب نقل معلق للموظف
     */
    public function findPendingTransferForEmployee(int $employeeId): ?Transfer
    {
        return Transfer::where('employee_id', $employeeId)
            ->where('status', NumericalStatusEnum::PENDING->value)
            ->first();
    }


    /**
     * تنفيذ النقل - تحديث بيانات الموظف
     */
    public function executeTransfer(Transfer $transfer): void
    {
        $employee = User::find($transfer->employee_id);

        if (!$employee) {
            throw new \Exception('الموظف غير موجود');
        }

        // تحديث البيانات الأساسية في ci_erp_users (الشركة فقط)
        $employeeUpdates = [];

        if ($transfer->new_company_id) {
            $employeeUpdates['company_id'] = $transfer->new_company_id;

            // جلب اسم الشركة الجديدة
            $newCompany = User::where('user_id', $transfer->new_company_id)
                ->where('user_type', 'company')
                ->first(['company_name']);

            if ($newCompany) {
                $employeeUpdates['company_name'] = $newCompany->company_name;
            }
        }

        if (!empty($employeeUpdates)) {
            $employee->update($employeeUpdates);
        }

        // تحديث البيانات التفصيلية في ci_erp_users_details
        $detailsUpdates = [];

        if ($transfer->new_company_id) {
            $detailsUpdates['company_id'] = $transfer->new_company_id;
        }

        if ($transfer->new_branch_id) {
            $detailsUpdates['branch_id'] = $transfer->new_branch_id;
        }

        if ($transfer->transfer_department) {
            $detailsUpdates['department_id'] = $transfer->transfer_department;
        }

        if ($transfer->transfer_designation) {
            $detailsUpdates['designation_id'] = $transfer->transfer_designation;
        }

        // المرتب (في ci_erp_users_details)
        if ($transfer->new_salary) {
            $detailsUpdates['basic_salary'] = $transfer->new_salary;
        }

        // العملة (في ci_erp_users_details)
        if ($transfer->new_currency) {
            $detailsUpdates['currency_id'] = $transfer->new_currency;
        }

        // المدير المباشر (إذا كان موجود في الـ transfer)
        if (isset($transfer->new_reporting_manager)) {
            $detailsUpdates['reporting_manager'] = $transfer->new_reporting_manager;
        }

        if (!empty($detailsUpdates)) {
            DB::table('ci_erp_users_details')
                ->where('user_id', $employee->user_id)
                ->update($detailsUpdates);
        }

        Log::info('Transfer executed - Employee data updated', [
            'transfer_id' => $transfer->transfer_id,
            'employee_id' => $employee->user_id,
            'employee_updates' => $employeeUpdates,
            'details_updates' => $detailsUpdates,
        ]);
    }

    /**
     * الحصول على الشركات مع الفروع للنقل بين الشركات
     */
    public function getCompaniesWithBranches(): array
    {
        return User::where('user_type', 'company')
            ->where('is_active', 1)
            ->with(['branches:branch_id,company_id,branch_name,description'])
            ->select(['user_id', 'company_name', 'trading_name'])
            ->get()
            ->map(function ($company) {
                return [
                    'company_id' => $company->user_id,
                    'company_name' => $company->company_name ?? $company->trading_name,
                    'branches' => $company->branches->map(function ($branch) {
                        return [
                            'branch_id' => $branch->branch_id,
                            'branch_name' => $branch->branch_name,
                        ];
                    })
                ];
            })
            ->toArray();
    }
    /**
     * الحصول على فروع الشركة
     */
    public function getBranchesByCompany(int $companyId): array
    {
        return \App\Models\Branch::where('company_id', $companyId)
            ->select(['branch_id', 'branch_name'])
            ->get()
            ->toArray();
    }
}
