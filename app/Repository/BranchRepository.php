<?php

namespace App\Repository;

use App\Models\Branch;
use App\Repository\Interface\BranchRepositoryInterface;
use Illuminate\Support\Collection;

class BranchRepository implements BranchRepositoryInterface
{
    public function getAllBranches(int $companyId, array $filters = []): mixed
    {
        $query = Branch::select('*')
            ->selectRaw('ST_AsText(coordinates) as coordinates_text')
            ->where('company_id', $companyId)
            ->withCount('userDetails');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('branch_name', 'LIKE', "%{$search}%");
        }

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (isset($filters['paginate']) && (bool)$filters['paginate'] === true) {
            $perPage = $filters['per_page'] ?? 10;
            return $query->paginate($perPage);
        }

        return $query->orderBy('branch_name', 'asc')->get();
    }

    public function getBranchById(int $id, int $companyId)
    {
        return Branch::select('*')
            ->selectRaw('ST_AsText(coordinates) as coordinates_text')
            ->where('branch_id', $id)
            ->where('company_id', $companyId)
            ->withCount('userDetails')
            ->first();
    }

    public function create(\App\DTOs\Branch\CreateBranchDTO $dto): Branch
    {
        $data = $dto->toArray();

        if (!empty($data['coordinates'])) {
            $data['coordinates'] = $this->formatCoordinatesForDb($data['coordinates']);
        }

        // Add created_at manually as we disabled timestamps
        $data['created_at'] = now()->toDateTimeString();

        $branch = Branch::create($data);

        return $this->getBranchById($branch->branch_id, $branch->company_id);
    }

    public function update(Branch $branch, \App\DTOs\Branch\UpdateBranchDTO $dto): Branch
    {
        $data = $dto->toArray();

        if (!empty($data['coordinates'])) {
            $data['coordinates'] = $this->formatCoordinatesForDb($data['coordinates']);
        }

        $branch->update($data);

        return $this->getBranchById($branch->branch_id, $branch->company_id);
    }

    /**
     * Format coordinates string for DB spatial insertion
     */
    private function formatCoordinatesForDb(string $coordinates): \Illuminate\Contracts\Database\Query\Expression
    {
        // If it's already WKT format (e.g. POLYGON, POINT)
        if (preg_match('/^POLYGON/i', $coordinates)) {
            return \Illuminate\Support\Facades\DB::raw("ST_GeomFromText('{$coordinates}')");
        }

        // If it's a POINT WKT, convert it to a degenerate POLYGON to match column type
        if (preg_match('/POINT\(([^ ]+) ([^ ]+)\)/i', $coordinates, $matches)) {
            $lng = $matches[1];
            $lat = $matches[2];
            $polygonWkt = "POLYGON(({$lng} {$lat}, {$lng} {$lat}, {$lng} {$lat}, {$lng} {$lat}))";
            return \Illuminate\Support\Facades\DB::raw("ST_GeomFromText('{$polygonWkt}')");
        }

        // If it's a simple lat,lng string, convert to a degenerate POLYGON
        if (preg_match('/^-?\d+\.?\d*,\s*-?\d+\.?\d*$/', $coordinates)) {
            $parts = explode(',', $coordinates);
            $lat = trim($parts[0]);
            $lng = trim($parts[1]);

            // Create a degenerate polygon (4 points, all the same) to satisfy POLYGON requirement
            $polygonWkt = "POLYGON(({$lng} {$lat}, {$lng} {$lat}, {$lng} {$lat}, {$lng} {$lat}))";
            return \Illuminate\Support\Facades\DB::raw("ST_GeomFromText('{$polygonWkt}')");
        }

        // Fallback: try wrapping in ST_GeomFromText anyway
        return \Illuminate\Support\Facades\DB::raw("ST_GeomFromText('{$coordinates}')");
    }

    public function delete(int $id, int $companyId): bool
    {
        $branch = $this->getBranchById($id, $companyId);
        if (!$branch) {
            return false;
        }
        return (bool) $branch->delete();
    }
}
