<?php

namespace App\Repository;

use App\Models\Country;
use App\Repository\Interface\CountryRepositoryInterface;
use Illuminate\Support\Collection;

class CountryRepository implements CountryRepositoryInterface
{
    public function getAllCountries(array $filters = []): mixed
    {
        $query = Country::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('country_name', 'LIKE', "%{$search}%")
                    ->orWhere('country_code', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($filters['country_id'])) {
            $query->where('country_id', $filters['country_id']);
        }

        if (!empty($filters['country_name'])) {
            $query->where('country_name', $filters['country_name']);
        }

        if (!empty($filters['country_code'])) {
            $query->where('country_code', $filters['country_code']);
        }

        if (isset($filters['paginate']) && (bool)$filters['paginate'] === true) {
            $perPage = $filters['per_page'] ?? 10;
            return $query->paginate($perPage);
        }

        return $query->orderBy('country_id', 'asc')->get();
    }

    public function getCountryById(int $id)
    {
        $query = Country::query();
        $query->where('country_id', $id);
        return $query->first();
    }
}
