<?php

namespace App\Repository;

use App\Repository\Interface\OvertimeRepositoryInterface;
use App\DTOs\Overtime\OvertimeRequestFilterDTO;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Models\UserDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OvertimeRepository implements OvertimeRepositoryInterface
{
    /**
     * Get paginated overtime requests with filters.
     */
    public function getPaginatedRequests(OvertimeRequestFilterDTO $filters, User $user): array
    {
        $query = OvertimeRequest::query()->with(['employee', 'approvals.staff']);

        // Apply company filter
        if ($filters->companyId) {
            $query->forCompany($filters->companyId);
        }

        // Apply employee filter (single employee OR list, not both)
        if ($filters->employeeId) {
            $query->forEmployee($filters->employeeId);
        } elseif ($filters->employeeIds !== null && is_array($filters->employeeIds) && !empty($filters->employeeIds)) {
            // Only apply employee IDs filter if single employee filter is not set
            $query->whereIn('staff_id', $filters->employeeIds);
        }

        // Apply status filter
        if ($filters->status !== null) {
            $query->where('is_approved', $filters->status);
        }

        // Apply overtime reason filter
        if ($filters->overtimeReason) {
            $query->where('overtime_reason', $filters->overtimeReason);
        }

        // Apply date range filter
        if ($filters->fromDate || $filters->toDate) {
            $query->dateRange($filters->fromDate, $filters->toDate);
        }

        // Apply month filter
        if ($filters->month) {
            $query->forMonth($filters->month);
        }

        // Apply search filter
        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                // Search in employee data
                $q->whereHas('employee', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('first_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm);
                });
                $q->whereHas('approvals.staff', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('first_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm);
                });
                $q->orWhere(function ($q) use ($searchTerm) {
                    // Search in request reason
                    $q->orWhere('request_reason', 'like', $searchTerm);
                    $q->orWhere('overtime_reason', 'like', $searchTerm);
                });
            });
        }

        // Apply hierarchy level filtering if specified
        // Use left join to avoid filtering out records without designation
        if ($filters->hierarchyLevels) {
            $query->whereHas('employee.user_details.designation', function ($q) use ($filters) {
                $q->whereIn('hierarchy_level', $filters->hierarchyLevels);
            });
        }

        // Order by most recent first
        $query->orderBy('time_request_id', 'desc');

        // Paginate
        $paginator = $query->paginate($filters->perPage, ['*'], 'page', $filters->page);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * Check if employee has overlapping overtime request
     */
    public function hasOverlappingOvertime(int $employeeId, string $requestDate, string $clockIn, string $clockOut, ?int $excludeRequestId = null): bool
    {
        Log::info('hasOverlappingOvertime called', [
            'employeeId' => $employeeId,
            'requestDate' => $requestDate,
            'clockIn' => $clockIn,
            'clockOut' => $clockOut,
            'excludeRequestId' => $excludeRequestId
        ]);

        // Convert request date and times to Carbon objects
        // Handle both time-only format (2:30 PM) and datetime format (2025-12-25 14:30:00)
        if (strpos($clockIn, ' ') !== false) {
            // Already datetime format
            $clockInDateTime = \Carbon\Carbon::parse($clockIn);
        } else {
            // Time only format, combine with date
            $clockInDateTime = \Carbon\Carbon::parse("{$requestDate} {$clockIn}");
        }

        if (strpos($clockOut, ' ') !== false) {
            // Already datetime format
            $clockOutDateTime = \Carbon\Carbon::parse($clockOut);
        } else {
            // Time only format, combine with date
            $clockOutDateTime = \Carbon\Carbon::parse("{$requestDate} {$clockOut}");
        }

        Log::info('Parsed datetime objects', [
            'clockInDateTime' => $clockInDateTime->format('Y-m-d H:i:s'),
            'clockOutDateTime' => $clockOutDateTime->format('Y-m-d H:i:s')
        ]);

        // Get existing overtime requests for the employee (pending and approved only)
        $existingRequests = OvertimeRequest::where('staff_id', $employeeId)
            ->whereIn('is_approved', [0, 1]) // 0: Pending, 1: Approved
            ->when($excludeRequestId, function ($q) use ($excludeRequestId) {
                $q->where('time_request_id', '!=', $excludeRequestId);
            })
            ->where('request_date', $requestDate)
            ->select(['time_request_id', 'request_date', 'clock_in', 'clock_out', 'is_approved'])
            ->get();

        Log::info('Found existing requests', [
            'count' => $existingRequests->count(),
            'requests' => $existingRequests->toArray()
        ]);

        // Check for time overlap
        foreach ($existingRequests as $request) {
            try {
                // Check if clock_in already contains date (datetime format) or just time
                if (strpos($request->clock_in, ' ') !== false) {
                    // Already datetime format
                    $existingClockIn = \Carbon\Carbon::parse($request->clock_in);
                } else {
                    // Time only format, combine with date
                    $existingClockIn = \Carbon\Carbon::parse("{$request->request_date} {$request->clock_in}");
                }

                // Check if clock_out already contains date (datetime format) or just time
                if (strpos($request->clock_out, ' ') !== false) {
                    // Already datetime format
                    $existingClockOut = \Carbon\Carbon::parse($request->clock_out);
                } else {
                    // Time only format, combine with date
                    $existingClockOut = \Carbon\Carbon::parse("{$request->request_date} {$request->clock_out}");
                }

                Log::info('Checking overlap with existing request', [
                    'existing_id' => $request->time_request_id,
                    'existing_clock_in' => $existingClockIn->format('Y-m-d H:i:s'),
                    'existing_clock_out' => $existingClockOut->format('Y-m-d H:i:s'),
                    'condition1' => $clockInDateTime->lt($existingClockOut),
                    'condition2' => $clockOutDateTime->gt($existingClockIn)
                ]);

                // Check if time ranges overlap
                if ($clockInDateTime->lt($existingClockOut) && $clockOutDateTime->gt($existingClockIn)) {
                    Log::info('Overtime overlap detected', [
                        'employee_id' => $employeeId,
                        'request_date' => $requestDate,
                        'new_clock_in' => $clockInDateTime->format('H:i'),
                        'new_clock_out' => $clockOutDateTime->format('H:i'),
                        'existing_id' => $request->time_request_id,
                        'existing_clock_in' => $existingClockIn->format('H:i'),
                        'existing_clock_out' => $existingClockOut->format('H:i'),
                        'existing_status' => $request->is_approved
                    ]);
                    return true;
                }
            } catch (\Exception $e) {
                Log::error('Error checking overtime overlap', [
                    'request_id' => $request->time_request_id ?? null,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        Log::info('No overlap found, returning false');
        return false;
    }

    /**
     * Create a new overtime request.
     */
    public function createRequest(array $data): OvertimeRequest
    {
        Log::info('OvertimeRepository::createRequest', ['data' => $data]);

        $request = OvertimeRequest::create($data);
        $request->load(['employee', 'approvals.staff']);

        return $request;
    }

    /**
     * Update an overtime request.
     */
    public function updateRequest(OvertimeRequest $request, array $data): OvertimeRequest
    {
        Log::info('OvertimeRepository::updateRequest', [
            'request_id' => $request->time_request_id,
            'data' => $data
        ]);

        $request->update($data);
        $request->refresh();
        $request->load(['employee', 'approvals.staff']);

        return $request;
    }

    /**
     * Delete an overtime request.
     */
    public function deleteRequest(OvertimeRequest $request): bool
    {
        Log::info('OvertimeRepository::deleteRequest', [
            'request_id' => $request->time_request_id
        ]);


        return $request->delete();
    }

    /**
     * Find overtime request by ID within company.
     */
    public function findRequestInCompany(int $requestId, int $companyId): ?OvertimeRequest
    {
        return OvertimeRequest::where('time_request_id', $requestId)
            ->forCompany($companyId)
            ->with(['employee', 'approvals.staff'])
            ->first();
    }

    /**
     * Approve an overtime request.
     */
    public function approveRequest(OvertimeRequest $request, int $approvedBy): OvertimeRequest
    {
        $request->update(['is_approved' => 1]);

        // Note: Approval recording is handled by ApprovalService to avoid duplicates

        $request->refresh();
        $request->load(['employee', 'approvals.staff']);

        return $request;
    }

    /**
     * Reject an overtime request.
     */
    public function rejectRequest(OvertimeRequest $request, int $rejectedBy, string $reason): OvertimeRequest
    {
        $request->update([
            'is_approved' => 2,
            'request_reason' => $request->request_reason
                ? $request->request_reason . ' | رفض: ' . $reason
                : 'رفض: ' . $reason
        ]);

        // Note: Rejection recording is handled by ApprovalService to avoid duplicates

        $request->refresh();
        $request->load(['employee', 'approvals.staff']);

        return $request;
    }

    /**
     * Get overtime requests by manager (subordinates).
     * Uses the reporting manager hierarchy from ci_erp_users_details.
     */
    public function getRequestsByManager(int $managerId, int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        // Get all subordinates using the same logic as old system
        $sql = "SELECT user_id
                FROM (SELECT * FROM ci_erp_users_details ORDER BY user_id) reporting_manager,
                (SELECT @pv := ?) initialisation
                WHERE FIND_IN_SET(reporting_manager, @pv)
                AND LENGTH(@pv := CONCAT(@pv, ',', user_id))";

        $subordinates = DB::select($sql, [$managerId]);
        $subordinateIds = array_map(fn($s) => $s->user_id, $subordinates);

        // Include the manager's own requests
        $subordinateIds[] = $managerId;

        return OvertimeRequest::whereIn('staff_id', $subordinateIds)
            ->forCompany($companyId)
            ->with(['employee', 'approvals.staff'])
            ->orderBy('time_request_id', 'desc')
            ->get();
    }

    /**
     * Get requests requiring approval from specific user.
     * Based on approval levels configured in UserDetails.
     */
    public function getRequestsRequiringApproval(int $userId, int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        // Get subordinates who have this user in their approval chain
        $subordinates = DB::table('ci_erp_users_details')
            ->where('company_id', $companyId)
            ->where(function ($query) use ($userId) {
                $query->where('approval_level01', $userId)
                    ->orWhere('approval_level02', $userId)
                    ->orWhere('approval_level03', $userId)
                    ->orWhere('reporting_manager', $userId);
            })
            ->pluck('user_id')
            ->toArray();

        if (empty($subordinates)) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        // Get pending requests from these subordinates
        $requests = OvertimeRequest::whereIn('staff_id', $subordinates)
            ->forCompany($companyId)
            ->pending()
            ->with(['employee', 'approvals.staff'])
            ->orderBy('time_request_id', 'desc')
            ->get();

        // Filter to only requests where this user should approve next
        $filtered = $requests->filter(function ($request) use ($userId) {
            $employeeDetails = UserDetails::where('user_id', $request->staff_id)->first();
            if (!$employeeDetails) {
                return false;
            }

            // Get approval chain
            $approvalChain = array_filter([
                $employeeDetails->approval_level01,
                $employeeDetails->approval_level02,
                $employeeDetails->approval_level03,
            ]);

            // Get current approval count
            $currentApprovals = $request->approvals()->forOvertime()->count();

            // Check if this user is the next approver
            if (isset($approvalChain[$currentApprovals]) && $approvalChain[$currentApprovals] == $userId) {
                return true;
            }

            // If no approval chain, check reporting manager
            if (empty($approvalChain) && $employeeDetails->reporting_manager == $userId) {
                return true;
            }

            return false;
        });

        return new \Illuminate\Database\Eloquent\Collection($filtered->values());
    }

    /**
     * Get statistics for company overtime requests.
     */
    public function getStats(int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = OvertimeRequest::forCompany($companyId);

        // Apply date range if provided
        if ($fromDate || $toDate) {
            $query->dateRange($fromDate, $toDate);
        }

        $totalRequests = $query->count();
        $pendingRequests = (clone $query)->pending()->count();
        $approvedRequests = (clone $query)->approved()->count();
        $rejectedRequests = (clone $query)->rejected()->count();

        // Calculate total overtime hours (sum of total_hours)
        $totalHours = (clone $query)->sum(DB::raw("TIME_TO_SEC(total_hours)"));
        $approvedHours = (clone $query)->approved()->sum(DB::raw("TIME_TO_SEC(total_hours)"));

        // Convert seconds back to H:i format
        $totalOvertimeHours = $this->secondsToHourMinute($totalHours);
        $approvedOvertimeHours = $this->secondsToHourMinute($approvedHours);

        // Group by overtime reason
        $byReason = (clone $query)
            ->select('overtime_reason', DB::raw('COUNT(*) as count'))
            ->groupBy('overtime_reason')
            ->get()
            ->map(function ($item) {
                $reasonValue = $item->overtime_reason instanceof \App\Enums\OvertimeReasonEnum
                    ? $item->overtime_reason->value
                    : $item->overtime_reason;
                return [
                    'reason' => $reasonValue,
                    'reason_text' => $this->getOvertimeReasonText($reasonValue),
                    'count' => $item->count,
                ];
            })
            ->toArray();

        // Group by compensation type
        $byCompensationType = (clone $query)
            ->select('compensation_type', DB::raw('COUNT(*) as count'))
            ->groupBy('compensation_type')
            ->get()
            ->map(function ($item) {
                $typeValue = $item->compensation_type instanceof \App\Enums\CompensationTypeEnum
                    ? $item->compensation_type->value
                    : $item->compensation_type;
                return [
                    'type' => $typeValue,
                    'type_text' => $typeValue == 1 ? 'Banked' : 'Payout',
                    'count' => $item->count,
                ];
            })
            ->toArray();

        return [
            'total_requests' => $totalRequests,
            'pending_requests' => $pendingRequests,
            'approved_requests' => $approvedRequests,
            'rejected_requests' => $rejectedRequests,
            'total_overtime_hours' => $totalOvertimeHours,
            'approved_overtime_hours' => $approvedOvertimeHours,
            'by_reason' => $byReason,
            'by_compensation_type' => $byCompensationType,
        ];
    }

    /**
     * Check if user can access overtime request.
     */
    public function canUserAccessRequest(User $user, OvertimeRequest $request): bool
    {
        // Company user can access all requests in their company
        if (strtolower($user->user_type) === 'company' && $request->company_id == $user->user_id) {
            return true;
        }

        // Staff user can access their own requests
        if ($request->staff_id == $user->user_id) {
            return true;
        }

        // Check if user is in the approval chain or is the reporting manager
        $employeeDetails = UserDetails::where('user_id', $request->staff_id)->first();
        if ($employeeDetails) {
            if (
                $employeeDetails->reporting_manager == $user->user_id ||
                $employeeDetails->approval_level01 == $user->user_id ||
                $employeeDetails->approval_level02 == $user->user_id ||
                $employeeDetails->approval_level03 == $user->user_id
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert seconds to H:i format.
     */
    private function secondsToHourMinute(?int $seconds): string
    {
        if (!$seconds) {
            return '0:00';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return sprintf('%d:%02d', $hours, $minutes);
    }

    /**
     * Get overtime reason text.
     */
    private function getOvertimeReasonText(int $reason): string
    {
        return match ($reason) {
            1 => 'Before Shift',
            2 => 'Work Through Lunch',
            3 => 'After Shift',
            4 => 'Weekend Work',
            5 => 'Additional Work',
            default => 'Unknown',
        };
    }
}
