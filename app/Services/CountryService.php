<?php

namespace App\Services;

use App\DTOs\Country\CountryFilterDTO;
use App\Repository\Interface\CountryRepositoryInterface;

class CountryService
{
    protected $countryRepository;

    public function __construct(CountryRepositoryInterface $countryRepository)
    {
        $this->countryRepository = $countryRepository;
    }

    public function getCountries(array $filters = [])
    {
        return $this->countryRepository->getAllCountries($filters);
    }

    public function getCountry(int $id)
    {
        return $this->countryRepository->getCountryById($id);
    }
}
