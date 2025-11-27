<?php

namespace App\DTOs\Leave;

class UpdateLeaveTypeDTO
{
    public function __construct(
        public  int $leaveTypeId,
        public  string $name,
        public  bool $requiresApproval = true,
        public  bool $isPaidLeave = false,
        public  bool $enableLeaveAccrual = false,
        public  bool $isCarry = false,
        public  float $carryLimit = 0,
        public  bool $isNegativeQuota = false,
        public  float $negativeLimit = 0,
        public  bool $isQuota = true,
        public  array $quotaAssign = [],
    ) {}

    public static function fromRequest(array $data): self
    {
        $requiresApproval = array_key_exists('requires_approval', $data)
            ? (bool) $data['requires_approval']
            : true;

        $isPaidLeave = array_key_exists('is_paid_leave', $data)
            ? (bool) $data['is_paid_leave']
            : false;

        $enableLeaveAccrual = array_key_exists('enable_leave_accrual', $data)
            ? (bool) $data['enable_leave_accrual']
            : false;

        $isCarry = array_key_exists('is_carry', $data)
            ? (bool) $data['is_carry']
            : false;

        $carryLimit = array_key_exists('carry_limit', $data)
            ? (float) $data['carry_limit']
            : 0;

        $isNegativeQuota = array_key_exists('is_negative_quota', $data)
            ? (bool) $data['is_negative_quota']
            : false;

        $negativeLimit = array_key_exists('negative_limit', $data)
            ? (float) $data['negative_limit']
            : 0;

        $isQuota = array_key_exists('is_quota', $data)
            ? (bool) $data['is_quota']
            : true;

        $quotaAssign = array_key_exists('quota_assign', $data) && is_array($data['quota_assign'])
            ? $data['quota_assign']
            : [];

        return new self(
            leaveTypeId: $data['leave_type_id'],
            name: $data['leave_type_name'],
            requiresApproval: $requiresApproval,
            isPaidLeave: $isPaidLeave,
            enableLeaveAccrual: $enableLeaveAccrual,
            isCarry: $isCarry,
            carryLimit: $carryLimit,
            isNegativeQuota: $isNegativeQuota,
            negativeLimit: $negativeLimit,
            isQuota: $isQuota,
            quotaAssign: $quotaAssign,
        );
    }

    public function toArray(): array
    {
        // Build options array to be serialized in field_one
        // Note: All values are stored as strings in the serialized array
        $quotaAssignStrings = [];
        foreach ($this->quotaAssign as $key => $value) {
            $quotaAssignStrings[$key] = (string) $value;
        }

        $options = [
            'enable_leave_accrual' => (string) ($this->enableLeaveAccrual ? 1 : 0),
            'is_carry' => (string) ($this->isCarry ? 1 : 0),
            'carry_limit' => (string) $this->carryLimit,
            'is_negative_quota' => (string) ($this->isNegativeQuota ? 1 : 0),
            'negative_limit' => (string) $this->negativeLimit,
            'is_quota' => (string) ($this->isQuota ? 1 : 0),
            'quota_assign' => $quotaAssignStrings,
        ];

        return [
            'category_name' => $this->name,
            'field_one' => serialize($options),
            'field_two' => $this->requiresApproval ? 1 : 0,
            'field_three' => $this->isPaidLeave ? 1 : 0,
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
