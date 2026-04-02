<?php

namespace App\DTOs\Leave;

class CreateLeaveTypeDTO
{
    public function __construct(
        public readonly int    $companyId,
        public readonly string $name,
        public readonly bool   $requiresApproval    = true,
        public readonly bool   $isPaidLeave         = false,
        public readonly bool   $enableLeaveAccrual  = true,
        public readonly bool   $isCarry             = false,
        public readonly float  $carryLimit          = 0,
        public readonly bool   $isNegativeQuota     = false,
        public readonly float  $negativeLimit       = 0,
        public readonly bool   $isQuota             = true,
        public readonly string $quotaUnit           = 'days',
        public readonly bool   $policyBased         = true,
        public readonly array  $quotaAssign         = [],
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        $requiresApproval = array_key_exists('requires_approval', $data)
            ? (bool) $data['requires_approval']
            : true;

        $isPaidLeave = array_key_exists('is_paid_leave', $data)
            ? (bool) $data['is_paid_leave']
            : false;

        $enableLeaveAccrual = array_key_exists('enable_leave_accrual', $data)
            ? (bool) $data['enable_leave_accrual']
            : true;

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

        $quotaUnit = array_key_exists('quota_unit', $data)
            ? (string) $data['quota_unit']
            : 'days';

        $policyBased = array_key_exists('policy_based', $data)
            ? (bool) $data['policy_based']
            : true;

        // يجب أن يحتوي على 50 عنصراً دائماً (السنة 1 → السنة 50)
        $quotaAssign = array_key_exists('quota_assign', $data) && is_array($data['quota_assign'])
            ? $data['quota_assign']
            : [];

        return new self(
            companyId: $companyId,
            name: $data['leave_type_name'],
            requiresApproval: $requiresApproval,
            isPaidLeave: $isPaidLeave,
            enableLeaveAccrual: $enableLeaveAccrual,
            isCarry: $isCarry,
            carryLimit: $carryLimit,
            isNegativeQuota: $isNegativeQuota,
            negativeLimit: $negativeLimit,
            isQuota: $isQuota,
            quotaUnit: $quotaUnit,
            policyBased: $policyBased,
            quotaAssign: $quotaAssign,
        );
    }

    public function toArray(): array
    {
        // بناء مصفوفة quota_assign بـ 50 عنصراً (integers) لتطابق الـ serialize الموجود في DB
        $quotaAssignIntegers = [];
        for ($i = 0; $i < 50; $i++) {
            $quotaAssignIntegers[$i] = isset($this->quotaAssign[$i]) ? (int) $this->quotaAssign[$i] : 0;
        }

        // الترتيب يطابق الـ serialize structure الموجود في قاعدة البيانات
        $options = [
            'is_quota'             => $this->isQuota             ? 1 : 0,
            'quota_assign'         => $quotaAssignIntegers,
            'quota_unit'           => $this->quotaUnit,
            'is_carry'             => $this->isCarry             ? 1 : 0,
            'carry_limit'          => (int) $this->carryLimit,
            'is_negative_quota'    => $this->isNegativeQuota     ? 1 : 0,
            'negative_limit'       => (int) $this->negativeLimit,
            'enable_leave_accrual' => $this->enableLeaveAccrual  ? 1 : 0,
            'policy_based'         => $this->policyBased         ? 1 : 0,
        ];

        return [
            'company_id'    => $this->companyId,
            'type'          => 'leave_type',
            'category_name' => $this->name,
            'field_one'     => serialize($options),
            'field_two'     => $this->requiresApproval ? 1 : 0,
            'field_three'   => $this->isPaidLeave      ? 1 : 0,
            'created_at'    => now()->format('Y-m-d H:i:s'),
        ];
    }
}
