<?php

namespace App\Services;

use App\DTOs\AdvanceSalary\AdvanceSalaryFilterDTO;
use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Overtime\OvertimeRequestFilterDTO;
use App\DTOs\Travel\TravelRequestFilterDTO;
use App\DTOs\CustodyClearance\CustodyClearanceFilterDTO;
use App\DTOs\Resignation\ResignationFilterDTO;
use App\DTOs\Transfer\TransferFilterDTO;
use App\Enums\NumericalStatusEnum;
use App\Enums\StringStatusEnum;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UnifiedRequestService
{
    public function __construct(
        protected LeaveService $leaveService,
        protected AdvanceSalaryService $advanceSalaryService,
        protected OvertimeService $overtimeService,
        protected TravelService $travelService,
        protected CustodyClearanceService $custodyClearanceService,
        protected ResignationService $resignationService,
        protected TransferService $transferService,
        protected EmployeeManagementService $employeeManagementService,
        protected SimplePermissionService $permissionService,
        protected HourlyLeaveService $hourlyLeaveService
    ) {}

    /**
     * Get all requests for a specific employee, grouped by type.
     * 
     * @param int $employeeId
     * @param User $authUser Current authenticated user
     * @return array
     * @throws \Exception
     */
    public function getEmployeeRequests(int $employeeId, User $authUser): array
    {
        $employee = User::findOrFail($employeeId);

        // Permission Check: Can the auth user view this employee's requests?
        if (!$this->permissionService->canViewEmployeeRequests($authUser, $employee)) {
            Log::warning('UnifiedRequestService::getEmployeeRequests - Permission denied', [
                'auth_user_id' => $authUser->user_id,
                'target_employee_id' => $employeeId
            ]);
            throw new \Exception('ليس لديك صلاحية لعرض طلبات هذا الموظف');
        }

        $results = [
            'leaves' => [],
            'advances' => [],
            'overtime' => [],
            'travels' => [],
            'custody_clearance' => [],
            'resignations' => [],
            'transfers' => [],
            'commissions' => [],
        ];

        // 1. Leaves
        try {
            $leaveFilters = LeaveApplicationFilterDTO::fromRequest([
                'employee_id' => $employeeId,
                'per_page' => 10 // Get a reasonable amount for profile view
            ]);
            $leaves = $this->leaveService->getPaginatedApplications($leaveFilters, $authUser);
            $leavesData = $leaves['data'] ?? $leaves;
            $results['leaves'] = collect($leavesData)->map(function ($item) {
                $status = data_get($item, 'status', 0);
                $leaveType = data_get($item, 'leave_type.category_name') ?: data_get($item, 'leaveType.category_name') ?: 'إجازة';
                return [
                    'id' => data_get($item, 'leave_id'),
                    'type' => $leaveType,
                    'from_date' => data_get($item, 'from_date'),
                    'to_date' => data_get($item, 'to_date'),
                    'status' => $status,
                    'status_text' => NumericalStatusEnum::tryFrom((int)$status)?->labelAr() ?? 'معلق',
                    'reason' => data_get($item, 'reason', ''),
                ];
            })->toArray();

            // 1.1 Hourly Leaves (Merge with leaves)
            try {
                $hourlyFilters = \App\DTOs\Leave\HourlyLeaveFilterDTO::fromRequest([
                    'employee_id' => $employeeId,
                    'per_page' => 10
                ]);
                $hourlyLeaves = $this->hourlyLeaveService->getPaginatedHourlyLeaves($hourlyFilters, $authUser);
                $hourlyData = $hourlyLeaves['data'] ?? [];

                $mappedHourly = collect($hourlyData)->map(function ($item) {
                    $status = data_get($item, 'status', 0);
                    $leaveType = data_get($item, 'leave_type.category_name') ?: data_get($item, 'leaveType.category_name') ?: 'إستئذان';
                    return [
                        'id' => data_get($item, 'leave_id'),
                        'type' => $leaveType,
                        'from_date' => data_get($item, 'from_date'),
                        'to_date' => data_get($item, 'from_date'), // It's a single day
                        'status' => $status,
                        'status_text' => NumericalStatusEnum::tryFrom((int)$status)?->labelAr() ?? 'معلق',
                        'reason' => data_get($item, 'reason', ''),
                        'is_hourly' => true,
                        'hours' => data_get($item, 'leave_hours', 0),
                    ];
                })->toArray();

                $results['leaves'] = array_merge($results['leaves'], $mappedHourly);
            } catch (\Exception $e) {
                Log::error('UnifiedRequestService - Error fetching hourly leaves', ['error' => $e->getMessage()]);
            }

            $results['leaves'] = $this->sanitizeUtf8($results['leaves']);
            if (json_encode($results['leaves']) === false) {
                Log::error('UnifiedRequestService::getEmployeeRequests - Leaves encoding failed', ['error' => json_last_error_msg()]);
                $results['leaves'] = ['error' => 'Encoding failed'];
            }
        } catch (\Exception $e) {
            Log::error('UnifiedRequestService - Error fetching leaves', ['error' => $e->getMessage()]);
        }

        // 2. Advances / Loans
        try {
            $advanceFilters = AdvanceSalaryFilterDTO::fromRequest([
                'employee_id' => $employeeId,
                'per_page' => 10
            ]);
            $advances = $this->advanceSalaryService->getPaginatedAdvances($advanceFilters, $authUser);
            $advancesData = $advances['data'] ?? [];
            $results['advances'] = collect($advancesData)->map(function ($item) {
                $status = data_get($item, 'status', 0);
                return [
                    'id' => data_get($item, 'advance_salary_id'),
                    'amount' => data_get($item, 'advance_amount', 0),
                    'monthly_installment' => data_get($item, 'monthly_installment', 0),
                    'status' => $status,
                    'status_text' => NumericalStatusEnum::tryFrom((int)$status)?->labelAr() ?? 'معلق',
                    'reason' => data_get($item, 'reason', ''),
                    'date' => data_get($item, 'month_year', ''),
                ];
            })->toArray();

            $results['advances'] = $this->sanitizeUtf8($results['advances']);
            if (json_encode($results['advances']) === false) {
                Log::error('UnifiedRequestService::getEmployeeRequests - Advances encoding failed', ['error' => json_last_error_msg()]);
                $results['advances'] = ['error' => 'Encoding failed'];
            }
        } catch (\Exception $e) {
            Log::error('UnifiedRequestService - Error fetching advances', ['error' => $e->getMessage()]);
        }

        // 3. Overtime
        try {
            $overtimeFilters = new OvertimeRequestFilterDTO(
                employeeId: $employeeId,
                perPage: 10
            );
            $overtime = $this->overtimeService->getPaginatedRequests($overtimeFilters, $authUser);
            $overtimeData = $overtime['data'] ?? [];
            $results['overtime'] = collect($overtimeData)->map(function ($item) {
                $status = data_get($item, 'is_approved', 0);
                return [
                    'id' => data_get($item, 'time_request_id'),
                    'num_hours' => data_get($item, 'total_hours', 0),
                    'status' => $status,
                    'status_text' => NumericalStatusEnum::tryFrom((int)$status)?->labelAr() ?? 'معلق',
                    'reason' => data_get($item, 'request_reason', ''),
                    'date' => data_get($item, 'request_date', ''),
                ];
            })->toArray();

            $results['overtime'] = $this->sanitizeUtf8($results['overtime']);
            if (json_encode($results['overtime']) === false) {
                Log::error('UnifiedRequestService::getEmployeeRequests - Overtime encoding failed', ['error' => json_last_error_msg()]);
                $results['overtime'] = ['error' => 'Encoding failed'];
            }
        } catch (\Exception $e) {
            Log::error('UnifiedRequestService - Error fetching overtime', ['error' => $e->getMessage()]);
        }

        // 4. Travels
        try {
            $travelFilters = new TravelRequestFilterDTO(
                employeeId: $employeeId,
                perPage: 10
            );
            $travels = $this->travelService->getTravels($authUser, $travelFilters);
            $travelsData = $travels['data'] ?? [];
            $results['travels'] = collect($travelsData)->map(function ($item) {
                $status = data_get($item, 'status', 0);
                return [
                    'id' => data_get($item, 'travel_id'),
                    'purpose' => data_get($item, 'visit_purpose', ''),
                    'place' => data_get($item, 'visit_place', ''),
                    'status' => $status,
                    'status_text' => NumericalStatusEnum::tryFrom((int)$status)?->labelAr() ?? 'معلق',
                    'start_date' => data_get($item, 'start_date')->format('Y-m-d'),
                    'end_date' => data_get($item, 'end_date')->format('Y-m-d'),
                ];
            })->toArray();

            $results['travels'] = $this->sanitizeUtf8($results['travels']);
            if (json_encode($results['travels']) === false) {
                Log::error('UnifiedRequestService::getEmployeeRequests - Travels encoding failed', ['error' => json_last_error_msg()]);
                $results['travels'] = ['error' => 'Encoding failed'];
            }
        } catch (\Exception $e) {
            Log::error('UnifiedRequestService - Error fetching travels', ['error' => $e->getMessage()]);
        }

        // 5. Custody Clearance
        try {
            $custodyFilters = CustodyClearanceFilterDTO::fromRequest([
                'employee_id' => $employeeId,
                'per_page' => 10
            ]);
            $custody = $this->custodyClearanceService->getPaginatedClearances($custodyFilters, $authUser);
            $custodyData = $custody['data'] ?? [];
            $results['custody_clearance'] = collect($custodyData)->map(function ($item) {
                $status = data_get($item, 'status', 'pending');
                return [
                    'id' => data_get($item, 'clearance_id'),
                    'date' => data_get($item, 'clearance_date'),
                    'status' => $status,
                    'status_text' => StringStatusEnum::tryFrom((string)$status)?->labelAr() ?? 'معلق',
                    'type_text' => data_get($item, 'clearance_type_text', ''),
                ];
            })->toArray();

            $results['custody_clearance'] = $this->sanitizeUtf8($results['custody_clearance']);
            if (json_encode($results['custody_clearance']) === false) {
                Log::error('UnifiedRequestService::getEmployeeRequests - Custody encoding failed', ['error' => json_last_error_msg()]);
                $results['custody_clearance'] = ['error' => 'Encoding failed'];
            }
        } catch (\Exception $e) {
            Log::error('UnifiedRequestService - Error fetching custody clearance', ['error' => $e->getMessage()]);
        }

        // 6. Resignations
        try {
            $resignationFilters = ResignationFilterDTO::fromRequest([
                'employee_id' => $employeeId,
                'per_page' => 10
            ]);
            $resignations = $this->resignationService->getPaginatedResignations($resignationFilters, $authUser);
            $resignationsData = $resignations['data'] ?? [];
            $results['resignations'] = collect($resignationsData)->map(function ($item) {
                $status = data_get($item, 'status', 0);
                return [
                    'id' => data_get($item, 'resignation_id'),
                    'date' => data_get($item, 'resignation_date'),
                    'status' => $status,
                    'status_text' => NumericalStatusEnum::tryFrom((int)$status)?->labelAr() ?? 'معلق',
                    'reason' => data_get($item, 'reason', ''),
                ];
            })->toArray();

            $results['resignations'] = $this->sanitizeUtf8($results['resignations']);
            if (json_encode($results['resignations']) === false) {
                Log::error('UnifiedRequestService::getEmployeeRequests - Resignations encoding failed', ['error' => json_last_error_msg()]);
                $results['resignations'] = ['error' => 'Encoding failed'];
            }
        } catch (\Exception $e) {
            Log::error('UnifiedRequestService - Error fetching resignations', ['error' => $e->getMessage()]);
        }

        // 7. Transfers
        try {
            $transferFilters = TransferFilterDTO::fromRequest([
                'employee_id' => $employeeId,
                'per_page' => 10
            ]);
            $transfers = $this->transferService->getPaginatedTransfers($transferFilters, $authUser);
            $transfersData = $transfers['data'] ?? [];
            $results['transfers'] = collect($transfersData)->map(function ($item) {
                $status = data_get($item, 'status', 0);
                return [
                    'id' => data_get($item, 'transfer_id'),
                    'date' => data_get($item, 'transfer_date'),
                    'status' => $status,
                    'status_text' => NumericalStatusEnum::tryFrom((int)$status)?->labelAr() ?? 'معلق',
                    'type_text' => (new \App\Models\Transfer(['transfer_type' => data_get($item, 'transfer_type', '')]))->transfer_type_text,
                    'from_dept' => data_get($item, 'old_department.department_name', 'غير محدد'),
                    'to_dept' => data_get($item, 'new_department.department_name', 'غير محدد'),
                ];
            })->toArray();

            $results['transfers'] = $this->sanitizeUtf8($results['transfers']);
            if (json_encode($results['transfers']) === false) {
                Log::error('UnifiedRequestService::getEmployeeRequests - Transfers encoding failed after sanitization', ['error' => json_last_error_msg()]);
                $results['transfers'] = ['error' => 'Encoding failed even after sanitization'];
            }
        } catch (\Exception $e) {
            Log::error('UnifiedRequestService - Error fetching transfers', ['error' => $e->getMessage()]);
        }

        // 8. Commissions / Bonuses
        try {
            $commissions = $this->employeeManagementService->getCommissions($authUser, $employeeId);
            $results['commissions'] = collect($commissions)->map(function ($item) {
                return [
                    'id' => data_get($item, 'payslip_commissions_id'),
                    'title' => data_get($item, 'pay_title', ''),
                    'amount' => data_get($item, 'pay_amount', 0),
                    'date' => data_get($item, 'created_at'),
                ];
            })->toArray();

            $results['commissions'] = $this->sanitizeUtf8($results['commissions']);
            if (json_encode($results['commissions']) === false) {
                Log::error('UnifiedRequestService::getEmployeeRequests - Commissions encoding failed', ['error' => json_last_error_msg()]);
                $results['commissions'] = ['error' => 'Encoding failed'];
            }
        } catch (\Exception $e) {
            Log::error('UnifiedRequestService - Error fetching commissions', ['error' => $e->getMessage()]);
        }

        if (request()->has('debug_counts')) {
            return [
                'leaves_count' => count($results['leaves']),
                'advances_count' => count($results['advances']),
                'overtime_count' => count($results['overtime']),
                'travels_count' => count($results['travels']),
                'custody_clearance_count' => count($results['custody_clearance']),
                'resignations_count' => count($results['resignations']),
                'transfers_count' => count($results['transfers']),
                'commissions_count' => count($results['commissions']),
            ];
        }

        return $results;
    }

    /**
     * Deeply sanitize an array/object to ensure all strings are valid UTF-8.
     * Handles Eloquent models, Collections, and standard objects.
     */
    private function sanitizeUtf8(mixed $data, int $depth = 0): mixed
    {
        if ($depth > 12) return null; // Safety limit

        if (is_string($data)) {
            // Check if it's binary data (e.g. spatial columns)
            if (str_contains($data, "\0")) {
                // Hex encode binary to be JSON safe
                return 'binary:' . bin2hex($data);
            }

            if (!mb_check_encoding($data, 'UTF-8')) {
                // Try common encodings. Use an array to be safer.
                // Using CP1256 instead of windows-1256 as it's a more common alias in some environments
                $encodings = ['UTF-8', 'ISO-8859-1', 'CP1256', 'IBM864'];

                // Filter out encodings that might not be supported by the current environment
                $supportedEncodings = array_filter($encodings, function ($encoding) {
                    try {
                        return @mb_encoding_aliases($encoding) !== false || @mb_check_encoding('', $encoding);
                    } catch (\Throwable $e) {
                        return false;
                    }
                });

                $detected = mb_detect_encoding($data, $supportedEncodings, true);
                return mb_convert_encoding($data, 'UTF-8', $detected ?: 'UTF-8');
            }
            return $data;
        }

        if ($data instanceof \Illuminate\Support\Collection) {
            return $data->map(fn($item) => $this->sanitizeUtf8($item, $depth + 1));
        }

        if ($data instanceof \Illuminate\Database\Eloquent\Model) {
            // Convert to array is most reliable for the final result
            return $this->sanitizeUtf8($data->toArray(), $depth + 1);
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitizeUtf8($value, $depth + 1);
            }
            return $data;
        }

        if (is_object($data)) {
            if ($data instanceof \stdClass) {
                foreach (get_object_vars($data) as $property => $value) {
                    $data->$property = $this->sanitizeUtf8($value, $depth + 1);
                }
            } else {
                try {
                    $newData = clone $data;
                    foreach (get_object_vars($data) as $property => $value) {
                        $newData->$property = $this->sanitizeUtf8($value, $depth + 1);
                    }
                    return $newData;
                } catch (\Exception $e) {
                    return '[Unclonable Object]';
                }
            }
            return $data;
        }

        return $data;
    }
}
