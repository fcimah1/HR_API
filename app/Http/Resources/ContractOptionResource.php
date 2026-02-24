<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'contract_option_id' => $this->contract_option_id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'type' => $this->salay_type,
            'contract_tax_option' => $this->contract_tax_option,
            'is_fixed' => $this->is_fixed,
            'option_title' => $this->option_title,
            'contract_amount' => $this->contract_amount,
        ];
    }
}
