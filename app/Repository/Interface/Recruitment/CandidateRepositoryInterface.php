<?php

namespace App\Repository\Interface\Recruitment;

use App\Models\Candidate;
use Illuminate\Pagination\LengthAwarePaginator;

interface CandidateRepositoryInterface
{
    public function getAll(array $filters = []): mixed;
    public function getById(int $id, int $companyId): ?Candidate;
    public function update(Candidate $candidate, array $data): bool;
    public function delete(Candidate $candidate): bool;
}
