<?php

namespace App\DTOs\Notification;

use Illuminate\Http\Request;

class NotificationSettingDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $moduleOption,
        public readonly array $notifyUponSubmission,
        public readonly array $notifyUponApproval,
        public readonly ?string $approvalMethod,
        public readonly ?int $approvalLevel,
        public readonly ?int $approvalLevel01,
        public readonly ?int $approvalLevel02,
        public readonly ?int $approvalLevel03,
        public readonly ?int $approvalLevel04,
        public readonly ?int $approvalLevel05,
        public readonly ?int $skipSpecificApproval,
    ) {}

    /**
     * Create DTO from request
     */
    public static function fromRequest(Request $request, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            moduleOption: $request->input('module_option'),
            notifyUponSubmission: $request->input('notify_upon_submission', []),
            notifyUponApproval: $request->input('notify_upon_approval', []),
            approvalMethod: $request->input('approval_method'),
            approvalLevel: $request->input('approval_level') ? (int)$request->input('approval_level') : null,
            approvalLevel01: $request->input('approval_level01') ? (int)$request->input('approval_level01') : null,
            approvalLevel02: $request->input('approval_level02') ? (int)$request->input('approval_level02') : null,
            approvalLevel03: $request->input('approval_level03') ? (int)$request->input('approval_level03') : null,
            approvalLevel04: $request->input('approval_level04') ? (int)$request->input('approval_level04') : null,
            approvalLevel05: $request->input('approval_level05') ? (int)$request->input('approval_level05') : null,
            skipSpecificApproval: $request->input('skip_specific_approval') ? (int)$request->input('skip_specific_approval') : 0,
        );
    }

    /**
     * Convert DTO to array for database
     */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'module_options' => $this->moduleOption,
            'notify_upon_submission' => !empty($this->notifyUponSubmission) ? implode(',', $this->notifyUponSubmission) : '',
            'notify_upon_approval' => !empty($this->notifyUponApproval) ? implode(',', $this->notifyUponApproval) : '',
            'approval_method' => $this->approvalMethod ?? '',
            'approval_level' => $this->approvalLevel ?? 0,
            'approval_level01' => $this->approvalLevel01 ?? 0,
            'approval_level02' => $this->approvalLevel02 ?? 0,
            'approval_level03' => $this->approvalLevel03 ?? 0,
            'approval_level04' => $this->approvalLevel04 ?? 0,
            'approval_level05' => $this->approvalLevel05 ?? 0,
            'skip_specific_approval' => $this->skipSpecificApproval ?? 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
}
