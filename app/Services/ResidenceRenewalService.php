<?php

namespace App\Services;

use App\DTOs\ResidenceRenewal\CreateResidenceRenewalDTO;
use App\Models\ResidenceRenewalCost;
use App\Models\UserDetails;
use App\Models\User;
use App\Repository\Interface\ResidenceRenewalRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResidenceRenewalService
{
    protected $renewalRepository;

    public function __construct(ResidenceRenewalRepositoryInterface $renewalRepository)
    {
        $this->renewalRepository = $renewalRepository;
    }

    public function getRenewals(int $companyId, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->renewalRepository->getRenewals($companyId, $filters, $perPage);
    }

    public function createRenewal(CreateResidenceRenewalDTO $dto): array
    {
        // 1. Fetch Employee Details
        $userDetails = UserDetails::with(['designation', 'user'])
            ->where('user_id', $dto->employeeId)
            ->where('company_id', $dto->companyId)
            ->first();

        if (!$userDetails) {
            throw new \Exception('بيانات الموظف غير موجودة', 404);
        }

        // 2. Perform Calculations
        $calculations = $this->calculateCosts([
            'work_start_date' => $userDetails->date_of_joining,
            'previous_expiry_date' => $userDetails->contract_date_eqama,
            'current_expiry_date' => $dto->currentResidenceExpiryDate,
            'work_permit_fee' => $dto->workPermitFee,
            'renewal_fees' => $dto->residenceRenewalFees,
            'penalty_amount' => $dto->penaltyAmount,
        ]);

        $employeeShare = $dto->isManualShares ? $dto->employeeShare : $calculations['employee_share'];
        $companyShare = $dto->isManualShares ? $dto->companyShare : $calculations['company_share'];

        // 3. Prepare Data for Save
        $data = array_merge($calculations, [
            'company_id' => $dto->companyId,
            'employee_id' => $dto->employeeId,
            'employee_name' => ($userDetails->user->first_name . ' ' . $userDetails->user->last_name),
            'profession' => $userDetails->designation?->designation_name ?? 'N/A',
            'work_start_date' => $userDetails->date_of_joining,
            'previous_residence_expiry_date' => $userDetails->contract_date_eqama,
            'current_residence_expiry_date' => $dto->currentResidenceExpiryDate,
            'work_permit_fee' => $dto->workPermitFee,
            'residence_renewal_fees' => $dto->residenceRenewalFees,
            'penalty_amount' => $dto->penaltyAmount,
            'employee_share' => $employeeShare,
            'company_share' => $companyShare,
            'grand_total' => round($employeeShare + $companyShare, 2),
            'notes' => $dto->notes,
            'created_at' => now(),
        ]);

        return DB::transaction(function () use ($data, $userDetails) {
            // Save Record
            $renewal = $this->renewalRepository->create($data);

            // Update Employee Detail (contract_date_eqama)
            $userDetails->update([
                'contract_date_eqama' => $data['current_residence_expiry_date']
            ]);

            // Add the ID to the top of the array for better visibility
            return array_merge(['renewal_cost_id' => $renewal->renewal_cost_id], $data);
        });
    }


    public function deleteRenewal(int $id, int $companyId): bool
    {
        $renewal = $this->renewalRepository->find($id, $companyId);
        if (!$renewal) {
            throw new \Exception('السجل غير موجود', 404);
        }

        return $this->renewalRepository->delete($renewal);
    }

    public function getRenewal(int $id, int $companyId): ?ResidenceRenewalCost
    {
        return $this->renewalRepository->find($id, $companyId);
    }

    /**
     * Logic based on User Requirements:
     * 1. Total Amount = Work Permit Fee + Renewal Fees + Penalty
     * 2. Days Until Expiry = Previous Expiry - Start Date
     * 3. Renewal Period Days = Current Expiry - Start Date
     * 4. Total Period Days = Above two summed
     * 5. Daily Rate = Renewal Fees / Total Period Days
     * 6. Employee Share = (Days Until Expiry * Daily Rate) + Penalty
     * 7. Company Share = (Renewal Period Days * Daily Rate) + Work Permit Fee
     * 8. Grand Total = Employee Share + Company Share
     */
    protected function calculateCosts(array $input): array
    {
        $start = Carbon::parse($input['work_start_date']);
        $prevExpiryStr = $input['previous_expiry_date'];

        // If previous expiry is null, use start date to avoid random drift, and set diff to 0
        if (!$prevExpiryStr) {
            $prevExpiry = $start->copy();
            $daysUntilExpiry = 0;
        } else {
            $prevExpiry = Carbon::parse($prevExpiryStr);
            $daysUntilExpiry = $start->diffInDays($prevExpiry);
        }

        $currExpiry = Carbon::parse($input['current_expiry_date']);

        $workPermitFee = (float) $input['work_permit_fee'];
        $renewalFees = (float) $input['renewal_fees'];
        $penalty = (float) $input['penalty_amount'];

        // Calculations
        $renewalPeriodDays = $start->diffInDays($currExpiry);
        $totalPeriodDays = $daysUntilExpiry + $renewalPeriodDays;

        if ($totalPeriodDays <= 0) {
            throw new \Exception('نطاق التواريخ غير صالح، إجمالي المدة يجب أن يكون أكبر من صفر', 400);
        }

        $dailyRate = $renewalFees / $totalPeriodDays;

        $employeeShare = ($daysUntilExpiry * $dailyRate) + $penalty;
        $companyShare = ($renewalPeriodDays * $dailyRate) + $workPermitFee;

        return [
            'total_amount' => $workPermitFee + $renewalFees + $penalty,
            'days_until_expiry' => (int) $daysUntilExpiry,
            'renewal_period_days' => (int) $renewalPeriodDays,
            'total_period_days' => (int) $totalPeriodDays,
            'daily_rate' => round($dailyRate, 3),
            'employee_share' => round($employeeShare, 2),
            'company_share' => round($companyShare, 2),
            'grand_total' => round($employeeShare + $companyShare, 2),
        ];
    }
}
