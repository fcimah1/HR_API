<?php

namespace App\DTOs\Leave;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateLeaveSettlementDTO
{
    public function __construct(
        public readonly int $employeeId,
        public readonly int $leaveTypeId,
        public readonly float $hoursToSettle,
        public readonly string $settlementType, // 'encashment' or 'take_leave'
        public readonly int $companyId,
        public readonly int $requestedBy
    ) {}

    /**
     * Create DTO from request
     *
     * @param array $data
     * @param int $companyId
     * @param int $requestedBy
     * @return self
     * @throws ValidationException
     */
    public static function fromRequest(array $data, int $companyId, int $requestedBy): self
    {
        $validator = Validator::make($data, [
            'leave_type_id' => 'required|integer',
            'hours_to_settle' => 'required|numeric|min:0.01',
            'settlement_type' => 'required|string|in:encashment,take_leave',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return new self(
            employeeId: $requestedBy, // Assuming the employee requesting is the one being settled for
            leaveTypeId: (int) $data['leave_type_id'],
            hoursToSettle: (float) $data['hours_to_settle'],
            settlementType: $data['settlement_type'],
            companyId: $companyId,
            requestedBy: $requestedBy
        );
    }
}