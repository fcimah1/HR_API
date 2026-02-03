<?php

namespace App\Services;

use App\DTOs\Promotion\CreatePromotionDTO;
use App\DTOs\Promotion\UpdatePromotionDTO;
use App\Models\Promotion;
use App\Models\UserDetails;
use App\Repository\Interface\PromotionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PromotionService
{
    protected $promotionRepository;

    public function __construct(PromotionRepositoryInterface $promotionRepository)
    {
        $this->promotionRepository = $promotionRepository;
    }

    public function getPromotions(int $companyId, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->promotionRepository->getPromotions($companyId, $filters, $perPage);
    }

    public function createPromotion(CreatePromotionDTO $dto): Promotion
    {
        // Get Current Employee Details to capture "old" values
        $userDetails = UserDetails::where('user_id', $dto->employeeId)
            ->where('company_id', $dto->companyId)
            ->first();

        $data = $dto->toArray();

        if ($userDetails) {
            $data['old_designation_id'] = $userDetails->designation_id;
            $data['old_department_id'] = $userDetails->department_id;
            $data['old_salary'] = $userDetails->basic_salary;
        }

        return $this->promotionRepository->create($data);
    }

    public function getPromotion(int $id, int $companyId): ?Promotion
    {
        return $this->promotionRepository->find($id, $companyId);
    }

    public function updatePromotion(int $id, UpdatePromotionDTO $dto, int $companyId): ?Promotion
    {
        $promotion = $this->promotionRepository->find($id, $companyId);

        if (!$promotion) {
            Log::error('Promotion not found', [
                'promotion_id' => $id,
                'company_id' => $companyId,
                'message' => 'الترقية غير موجودة'
            ]);
            throw new \Exception(message: 'الترقية غير موجودة', code: 404);
        }

        // Check if status allows editing (Only Pending = 0 can be updated/approved)
        if ($promotion->status !== 0) {
            Log::error('Promotion not found', [
                'promotion_id' => $id,
                'company_id' => $companyId,
                'message' => 'لا يمكن تعديل ترقية تم قبولها أو رفضها مسبقاً'
            ]);
            throw new \Exception(message: 'لا يمكن تعديل ترقية تم قبولها أو رفضها مسبقاً', code: 400);
        }

        return DB::transaction(function () use ($promotion, $dto) {
            $updatedPromotion = $this->promotionRepository->update($promotion, $dto->toArray());

            // If Status is updated to Approved (1), update UserDetails
            if ($dto->status === 1) {
                $this->applyPromotionToEmployee($updatedPromotion);
            }

            return $updatedPromotion;
        });
    }

    public function deletePromotion(int $id, int $companyId): bool
    {
        $promotion = $this->promotionRepository->find($id, $companyId);

        if (!$promotion) {
            Log::error('Promotion not found', [
                'promotion_id' => $id,
                'company_id' => $companyId,
                'message' => 'الترقية غير موجودة'
            ]);
            throw new \Exception(message: 'الترقية غير موجودة', code: 404);
        }

        // Check if status allows deletion (Only Pending = 0 can be deleted)
        if ($promotion->status !== 0) {
            Log::error('Promotion not found', [
                'promotion_id' => $id,
                'company_id' => $companyId,
                'message' => 'لا يمكن حذف ترقية تم قبولها أو رفضها مسبقاً'
            ]);
            throw new \Exception(message: 'لا يمكن حذف ترقية تم قبولها أو رفضها مسبقاً', code: 400);
        }

        return $this->promotionRepository->delete($promotion);
    }

    /**
     * Apply the promotion changes to the actual employee record.
     */
    protected function applyPromotionToEmployee(Promotion $promotion): void
    {
        $userDetails = UserDetails::where('user_id', $promotion->employee_id)
            ->where('company_id', $promotion->company_id)
            ->first();

        if ($userDetails) {
            $userDetails->update([
                'designation_id' => $promotion->new_designation_id,
                'department_id' => $promotion->new_department_id,
                'basic_salary' => $promotion->new_salary,
            ]);
        }
    }
}
