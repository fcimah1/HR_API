<?php

namespace App\DTOs\Finance;

use Illuminate\Http\Request;

class UpdateTransactionDTO
{
    public function __construct(
        public readonly int $transactionId,
        public readonly int $companyId,
        public readonly int $accountId,
        public readonly int $staffId,
        public readonly string $transactionDate,
        public readonly float $amount,
        public readonly ?int $entityId = 0,
        public readonly ?string $entityType = null,
        public readonly ?int $entityCategoryId = 0,
        public readonly ?string $description = null,
        public readonly ?int $paymentMethodId = 0,
        public readonly ?string $reference = null,
        public readonly ?string $attachmentFile = null
    ) {}

    public static function fromRequest(int $id, Request $request, int $companyId, int $staffId): self
    {
        return new self(
            transactionId: $id,
            companyId: $companyId,
            accountId: (int) $request->input('account_id'),
            staffId: $staffId,
            transactionDate: $request->input('transaction_date', date('Y-m-d')),
            amount: (float) $request->input('amount'),
            entityId: (int) $request->input('employee_id', 0),
            entityType: $request->input('entity_type'),
            entityCategoryId: (int) $request->input('entity_category_id', 0),
            description: $request->input('description'),
            paymentMethodId: (int) $request->input('payment_method_id', 0),
            reference: $request->input('reference'),
            attachmentFile: $request->input('attachment_file')
        );
    }

    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'staff_id' => $this->staffId,
            'transaction_date' => $this->transactionDate,
            'amount' => $this->amount,
            'entity_id' => $this->entityId,
            'entity_type' => $this->entityType,
            'entity_category_id' => $this->entityCategoryId,
            'description' => $this->description,
            'payment_method_id' => $this->paymentMethodId,
            'reference' => $this->reference,
            'attachment_file' => $this->attachmentFile,
        ];
    }
}
