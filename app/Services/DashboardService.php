<?php

namespace App\Services;

use App\Models\User;
use App\Models\OvertimeRequest;
use App\Models\AdvanceSalary;
use App\Models\LeaveApplication;
use Illuminate\Support\Facades\DB;
use App\Services\LeaveService;
use App\Repository\Interface\OvertimeRepositoryInterface;
use App\Repository\Interface\TravelRepositoryInterface;
use App\Repository\Interface\AdvanceSalaryRepositoryInterface;
use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Overtime\OvertimeRequestFilterDTO;
use App\DTOs\Travel\TravelRequestFilterDTO;
use App\DTOs\AdvanceSalary\AdvanceSalaryFilterDTO;
use App\DTOs\Resignation\ResignationFilterDTO;
use App\DTOs\Leave\HourlyLeaveFilterDTO;
use App\DTOs\LeaveAdjustment\LeaveAdjustmentFilterDTO;
use App\DTOs\Complaint\ComplaintFilterDTO;
use App\DTOs\Transfer\TransferFilterDTO;
use App\Repository\Interface\ResignationRepositoryInterface;
use App\Repository\Interface\HourlyLeaveRepositoryInterface;
use App\Repository\Interface\LeaveAdjustmentRepositoryInterface;
use App\Repository\Interface\ComplaintRepositoryInterface;
use App\Repository\Interface\TransferRepositoryInterface;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use App\Enums\NumericalStatusEnum;

class DashboardService
{
    protected $leaveService;
    protected $overtimeRepository;
    protected $travelRepository;
    protected $advanceSalaryRepository;
    protected $resignationRepository;
    protected $hourlyLeaveRepository;
    protected $leaveAdjustmentRepository;
    protected $complaintRepository;
    protected $transferRepository;

    public function __construct(
        LeaveService $leaveService,
        OvertimeRepositoryInterface $overtimeRepository,
        TravelRepositoryInterface $travelRepository,
        AdvanceSalaryRepositoryInterface $advanceSalaryRepository,
        ResignationRepositoryInterface $resignationRepository,
        HourlyLeaveRepositoryInterface $hourlyLeaveRepository,
        LeaveAdjustmentRepositoryInterface $leaveAdjustmentRepository,
        ComplaintRepositoryInterface $complaintRepository,
        TransferRepositoryInterface $transferRepository
    ) {
        $this->leaveService = $leaveService;
        $this->overtimeRepository = $overtimeRepository;
        $this->travelRepository = $travelRepository;
        $this->advanceSalaryRepository = $advanceSalaryRepository;
        $this->resignationRepository = $resignationRepository;
        $this->hourlyLeaveRepository = $hourlyLeaveRepository;
        $this->leaveAdjustmentRepository = $leaveAdjustmentRepository;
        $this->complaintRepository = $complaintRepository;
        $this->transferRepository = $transferRepository;
    }

    /**
     * Get consumption statistics for the user
     */
    public function getConsumptionStats(User $user): array
    {
        // 1. Leave Stats
        // LeaveService::getMonthlyLeaveStatistics returns detailed breakdown by leave type.
        // We need to aggregate these values for a "Total" overview.
        $leaveStatsRaw = $this->leaveService->getMonthlyLeaveStatistics($user->user_id, $user->company_id);

        $totalGranted = 0;
        $totalUsed = 0;
        $totalBalance = 0;
        $totalPending = 0; // The current service doesn't return pending in this structure, might need separate query or adjustment.

        // Aggregate across all leave types
        foreach (($leaveStatsRaw['leave_types'] ?? []) as $typeStr) {
            // Each type has 'monthly_breakdown'
            foreach (($typeStr['monthly_breakdown'] ?? []) as $monthData) {
                $totalGranted += $monthData['granted'] ?? 0;
                $totalUsed += $monthData['used'] ?? 0;
            }
        }
        $totalBalance = $totalGranted - $totalUsed;

        // Pending is distinct, fetching separately via repository or count
        $totalPending = LeaveApplication::where('employee_id', $user->user_id)
            ->where('status', LeaveApplication::STATUS_PENDING)
            ->count();

        $leaveSummary = [
            'granted_in_days' => $totalGranted / 8,
            'granted_in_hours' => $totalGranted,
            'used_in_days' => $totalUsed / 8,
            'used_in_hours' => $totalUsed,
            'balance_in_days' => $totalBalance / 8,
            'balance_in_hours' => $totalBalance,
            'pending_requests' => $totalPending,
        ];

        // 2. Overtime Stats (Total Approved Hours)
        // using DB query directly for efficiency as Repository doesn't have user-specific stats method yet
        $overtimeHoursSeconds = OvertimeRequest::where('staff_id', $user->user_id)
            ->where('is_approved', 1) // Approved
            ->sum(DB::raw("TIME_TO_SEC(total_hours)"));

        $overtimeHours = floor($overtimeHoursSeconds / 3600);
        $overtimeMinutes = floor(($overtimeHoursSeconds % 3600) / 60);
        $overtimeString = sprintf('%02d:%02d', $overtimeHours, $overtimeMinutes);

        // 3. Travel Stats (Total, Pending, Approved)
        $travelTotal = \App\Models\Travel::where('employee_id', $user->user_id)->count();
        $travelPending = \App\Models\Travel::where('employee_id', $user->user_id)->where('status', 0)->count();
        $travelApproved = \App\Models\Travel::where('employee_id', $user->user_id)->where('status', 1)->count();

        // 4. Advance Salary / Loans (Total Remaining, Installments details)
        $approvedLoans = AdvanceSalary::where('employee_id', $user->user_id)
            ->where('status', 1) // Approved
            ->get();

        $totalLoans = $approvedLoans->count();
        $totalRemainingAmount = 0;
        $totalInstallments = 0;
        $paidInstallments = 0;

        foreach ($approvedLoans as $loan) {
            $remaining = $loan->advance_amount - $loan->total_paid;
            $totalRemainingAmount += $remaining;

            if ($loan->monthly_installment > 0) {
                $totalInstallments += (int) ceil($loan->advance_amount / $loan->monthly_installment);
                $paidInstallments += (int) floor($loan->total_paid / $loan->monthly_installment);
            }
        }

        // 5. Hourly Leave (Total Approved Hours)
        // Check 12 -> Hourly Leave Type. This might vary per company, finding generic 'Hourly' type or assuming ID
        // Alternatively, filter by leave_hours being not null and < 8
        $hourlyLeaveHours = LeaveApplication::where('employee_id', $user->user_id)
            ->where('status', LeaveApplication::STATUS_APPROVED)
            ->whereNotNull('leave_hours')
            ->sum('leave_hours');

        $hourlyLeavePending = LeaveApplication::where('employee_id', $user->user_id)
            ->where('status', LeaveApplication::STATUS_PENDING)
            ->whereNotNull('leave_hours')
            ->sum('leave_hours');

        $hourlyLeaveApproved = LeaveApplication::where('employee_id', $user->user_id)
            ->where('status', LeaveApplication::STATUS_APPROVED)
            ->whereNotNull('leave_hours')
            ->sum('leave_hours');

        return [
            'leaves' => $leaveSummary,
            'overtime' => [
                'approved_hours' => $overtimeString, // Format H:i
                'total_seconds' => (int)$overtimeHoursSeconds,
                'pending_requests' => OvertimeRequest::where('staff_id', $user->user_id)->where('is_approved', 0)->count(),
                'approved_requests' => OvertimeRequest::where('staff_id', $user->user_id)->where('is_approved', 1)->count()
            ],
            'travel' => [
                'total_trips' => $travelTotal,
                'pending_trips' => $travelPending,
                'approved_trips' => $travelApproved
            ],
            'loans' => [
                'total_loans' => $totalLoans,
                'remaining_amount' => number_format((float)$totalRemainingAmount, 2),
                'total_installments' => $totalInstallments,
                'paid_installments' => $paidInstallments,
                'currency' => 'SAR'
            ],
            'hourly_leave' => [
                'total_hours' => number_format((float)$hourlyLeaveHours, 1),
                'pending_hours' => number_format((float)$hourlyLeavePending, 1),
                'approved_hours' => number_format((float)$hourlyLeaveApproved, 1),
            ]
        ];
    }

    /**
     * Get recent activity across all services
     */
    public function getRecentActivity(User $user): array
    {
        $activities = new Collection();
        $limit = 5;

        // 1. Recent Leaves
        $leaveFilter = new LeaveApplicationFilterDTO(
            employeeId: $user->user_id,
            companyId: $user->company_id, // Important for context
            perPage: $limit,
            page: 1
        );
        // LeaveService has getPaginatedApplications helper, using that or repository directly?
        // Let's use repository via Service or assume Service has it.
        // Actually, DashService has leaveService injected.
        $leaves = $this->leaveService->getPaginatedApplications($leaveFilter, $user);
        // $leaves is array with 'data' key
        foreach (($leaves['data'] ?? []) as $item) {
            $activities->push([
                'type' => 'leave',
                'title' => 'Leave Request',
                'status' => $item['status'] ?? 'unknown',
                'status_name' => NumericalStatusEnum::from($item['status'])->name, //use enum NumericalStatusEnum casess
                'date' => Carbon::parse($item['created_at'] ?? now()), // Ensure Carbon object
                'formatted_date' => Carbon::parse($item['created_at'] ?? now())->diffForHumans(),
                'details' => $item['leave_type_name'] ?? 'Leave',
                'id' => $item['leave_id'] ?? $item['id'] ?? 0
            ]);
        }

        // 2. Recent Overtime
        $overtimeFilter = new OvertimeRequestFilterDTO(
            employeeId: $user->user_id,
            companyId: $user->company_id,
            perPage: $limit,
            page: 1
        );
        $overtimes = $this->overtimeRepository->getPaginatedRequests($overtimeFilter, $user);
        foreach (($overtimes['data'] ?? []) as $item) {
            $activities->push([
                'type' => 'overtime',
                'title' => 'Overtime Request',
                'status' => $item['status'] ?? 'unknown',
                'status_name' => NumericalStatusEnum::from($item['status'])->name, //use enum NumericalStatusEnum casess
                'date' => Carbon::parse($item['created_at'] ?? now()),
                'formatted_date' => Carbon::parse($item['created_at'] ?? now())->diffForHumans(),
                'details' => $item['overtime_reason'] ?? 'Overtime',
                'id' => $item['time_request_id'] ?? $item['id'] ?? 0
            ]);
        }

        // 3. Recent Travel
        $travelFilter = new TravelRequestFilterDTO(perPage: $limit, page: 1);
        $travels = $this->travelRepository->getByEmployee($user->user_id, $travelFilter);
        // Check if travel returns 'data' or just array. Usually 'data' in pagination struct.
        foreach (($travels['data'] ?? []) as $item) {
            $activities->push([
                'type' => 'travel',
                'title' => 'Trip Request',
                'status' => $item['status'] ?? 'unknown',
                'status_name' => NumericalStatusEnum::from($item['status'])->name, //use enum NumericalStatusEnum casess
                'date' => Carbon::parse($item['created_at'] ?? now()),
                'formatted_date' => Carbon::parse($item['created_at'] ?? now())->diffForHumans(),
                'details' => $item['destination'] ?? 'Travel',
                'id' => $item['travel_id'] ?? $item['id'] ?? 0
            ]);
        }

        // 4. Recent Loans
        $advanceFilter = new AdvanceSalaryFilterDTO(
            employeeId: $user->user_id,
            companyId: $user->company_id,
            perPage: $limit
        );
        $advances = $this->advanceSalaryRepository->getPaginatedAdvances($advanceFilter);
        foreach ($advances->items() as $item) {
            // Check if item is array or object (Model)
            $itemObj = is_array($item) ? (object) $item : $item;
            $activities->push([
                'type' => 'loan',
                'title' => 'Loan Request',
                'status' => $itemObj->status ?? 'unknown',
                'status_name' => NumericalStatusEnum::from($itemObj->status)->name, //use enum NumericalStatusEnum casess
                'date' => Carbon::parse($itemObj->created_at ?? now()),
                'formatted_date' => Carbon::parse($itemObj->created_at ?? now())->diffForHumans(),
                'details' => number_format((float)($itemObj->amount ?? 0), 2),
                'id' => $itemObj->advance_salary_id ?? $itemObj->id ?? 0
            ]);
        }

        // 5. Resignation
        $resignationFilter = new ResignationFilterDTO(employeeId: $user->user_id, perPage: $limit);
        $resignations = $this->resignationRepository->getPaginatedResignations($resignationFilter, $user);
        foreach (($resignations['data'] ?? []) as $item) {
            $activities->push([
                'type' => 'resignation',
                'title' => 'Resignation Request',
                'status' => $item['status'] ?? 'unknown',
                'status_name' => NumericalStatusEnum::from($item['status'])->name, //use enum NumericalStatusEnum casess
                'date' => Carbon::parse($item['created_at'] ?? now()),
                'formatted_date' => Carbon::parse($item['created_at'] ?? now())->diffForHumans(),
                'details' => 'Resignation',
                'id' => $item['id'] ?? 0
            ]);
        }

        // 6. Hourly Leave
        $hourlyFilter = new HourlyLeaveFilterDTO(employeeId: $user->user_id, perPage: $limit);
        $hourlyLeaves = $this->hourlyLeaveRepository->getPaginatedHourlyLeaves($hourlyFilter, $user);
        foreach (($hourlyLeaves['data'] ?? []) as $item) {
            $activities->push([
                'type' => 'hourly_leave',
                'title' => 'Hourly Leave Request',
                'status' => $item['status'] ?? 'unknown',
                'status_name' => NumericalStatusEnum::from($item['status'])->name, //use enum NumericalStatusEnum casess
                'date' => Carbon::parse($item['created_at'] ?? now()),
                'formatted_date' => Carbon::parse($item['created_at'] ?? now())->diffForHumans(),
                'details' => ($item['leave_type_name'] ?? 'Hourly Leave') . ' (' . ($item['hourly_leave_period'] ?? '') . ')',
                'id' => $item['id'] ?? $item['leave_id'] ?? 0
            ]);
        }

        // 7. Leave Adjustment
        $adjustmentFilter = new LeaveAdjustmentFilterDTO(employeeId: $user->user_id, perPage: $limit);
        $adjustments = $this->leaveAdjustmentRepository->getPaginatedAdjustments($adjustmentFilter);
        foreach (($adjustments['data'] ?? []) as $item) {
            $activities->push([
                'type' => 'adjustment',
                'title' => 'Leave Adjustment',
                'status' => $item['status'] ?? 'unknown',
                'status_name' => NumericalStatusEnum::from($item['status'])->name, //use enum NumericalStatusEnum casess
                'date' => Carbon::parse($item['created_at'] ?? now()), // Usually created_at
                'formatted_date' => Carbon::parse($item['created_at'] ?? now())->diffForHumans(),
                'details' => ($item['adjust_hours'] ?? 0) . ' Hours',
                'id' => $item['id'] ?? 0
            ]);
        }

        // 8. Complaint
        $complaintFilter = new ComplaintFilterDTO(employeeId: $user->user_id, perPage: $limit);
        $complaints = $this->complaintRepository->getPaginatedComplaints($complaintFilter, $user);
        foreach (($complaints['data'] ?? []) as $item) {
            $activities->push([
                'type' => 'complaint',
                'title' => 'Complaint',
                'status' => $item['status'] ?? 'unknown',
                'status_name' => NumericalStatusEnum::from($item['status'])->name, //use enum NumericalStatusEnum casess
                'date' => Carbon::parse($item['created_at'] ?? now()),
                'formatted_date' => Carbon::parse($item['created_at'] ?? now())->diffForHumans(),
                'details' => 'Complaint',
                'id' => $item['complaint_id'] ?? $item['id'] ?? 0
            ]);
        }

        // 9. Transfer
        $transferFilter = new TransferFilterDTO(employeeId: $user->user_id, perPage: $limit);
        $transfers = $this->transferRepository->getPaginatedTransfers($transferFilter, $user);
        foreach (($transfers['data'] ?? []) as $item) {
            $activities->push([
                'type' => 'transfer',
                'title' => 'Transfer Request',
                'status' => $item['status'] ?? 'unknown',
                'status_name' => NumericalStatusEnum::from($item['status'])->name, //use enum NumericalStatusEnum casess
                'date' => Carbon::parse($item['created_at'] ?? now()),
                'formatted_date' => Carbon::parse($item['created_at'] ?? now())->diffForHumans(),
                'details' => 'Transfer',
                'id' => $item['transfer_id'] ?? $item['id'] ?? 0
            ]);
        }

        // Sort by date desc and take top 5
        return $activities->sortByDesc('date')->take(5)->values()->toArray();
    }
}
