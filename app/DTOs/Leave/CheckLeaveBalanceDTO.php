<?php

namespace App\DTOs\Leave;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class CheckLeaveBalanceDTO
{
    public function __construct(
        public  int $employeeId,
        public  int $leaveTypeId,
        public  string $startDate,
        public  string $endDate,
        public  int $companyId
    ) {}

    /**
     * Create DTO from request
     *
     * @param Request $request
     * @param int $companyId
     * @return self
     * @throws ValidationException
     */
    public static function fromRequest(Request $request, int $companyId): self
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'leave_type_id' => 'required|integer|exists:leave_types,id',
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return new self(
            employeeId: (int) $request->input('employee_id'),
            leaveTypeId: (int) $request->input('leave_type_id'),
            startDate: $request->input('start_date'),
            endDate: $request->input('end_date'),
            companyId: $companyId
        );
    }

    /**
     * Calculate requested days
     *
     * @return int
     */
    public function getRequestedDays(): int
    {
        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);
        return $start->diffInDays($end) + 1; // +1 to include the start date
    }
}