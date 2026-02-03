<?php

namespace App\Repository\Interface;

interface AwardConfigurationRepositoryInterface
{
    public function getTypes(int $companyId, ?string $search = null);
    public function create(array $data);
    public function find(int $id, int $companyId);
    public function update(int $id, int $companyId, array $data);
    public function delete(int $id, int $companyId);
}
