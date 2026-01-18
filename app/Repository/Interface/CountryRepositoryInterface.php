<?php

namespace App\Repository\Interface;

use Illuminate\Support\Collection;

interface CountryRepositoryInterface
{
    public function getAllCountries(array $filters = []): Collection;
    public function getCountryById(int $id);
}
