<?php

namespace App\Repository;

use App\Models\ErpConstant;
use App\Repository\Interface\AwardConfigurationRepositoryInterface;

class AwardConfigurationRepository implements AwardConfigurationRepositoryInterface
{
    public function getTypes(int $companyId, ?string $search = null)
    {
        return ErpConstant::where('company_id', $companyId)
            ->where('type', ErpConstant::TYPE_AWARD_TYPE)
            ->when($search, function ($query) use ($search) {
                $query->where('category_name', 'like', "%{$search}%");
            })
            ->select('constants_id', 'category_name', 'created_at')
            ->get();
    }

    public function create(array $data)
    {
        $config = new ErpConstant();
        $config->company_id = $data['company_id'];
        $config->type = ErpConstant::TYPE_AWARD_TYPE;
        $config->category_name = $data['category_name'];
        $config->created_at = now();
        $config->save();

        return $config;
    }

    public function find(int $id, int $companyId)
    {
        return ErpConstant::where('company_id', $companyId)
            ->where('type', ErpConstant::TYPE_AWARD_TYPE)
            ->where('constants_id', $id)
            ->first();
    }

    public function update(int $id, int $companyId, array $data)
    {
        $config = $this->find($id, $companyId);

        if ($config) {
            $config->category_name = $data['category_name'];
            $config->save();
            return $config;
        }

        return null;
    }

    public function delete(int $id, int $companyId)
    {
        $config = $this->find($id, $companyId);

        if ($config) {
            return $config->delete();
        }

        return false;
    }
}
